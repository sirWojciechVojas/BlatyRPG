<?php

namespace App\Services\Audio;

use App\Models\AudioPlaylistItemModel;
use App\Models\AudioPlaylistModel;
use App\Models\CampaignAudioTrackModel;
use App\Models\CampaignJukeboxQueueItemModel;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

/** Owns persistent playlists and per-channel queues; playback remains realtime. */
final class JukeboxPlanningService
{
    private const MAX_PLAYLISTS = 100;
    private const MAX_PLAYLIST_ITEMS = 500;
    private const MAX_QUEUE_ITEMS = 500;

    private $db;
    private $guard;
    private $playlists;
    private $playlistItems;
    private $queueItems;
    private $campaignTracks;

    public function __construct(?BaseConnection $db = null, ?CampaignGuardService $guard = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->playlists = new AudioPlaylistModel($this->db);
        $this->playlistItems = new AudioPlaylistItemModel($this->db);
        $this->queueItems = new CampaignJukeboxQueueItemModel($this->db);
        $this->campaignTracks = new CampaignAudioTrackModel($this->db);
    }

    public function snapshot(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        return [
            'playlists' => $this->isGameMaster($context) ? $this->playlistRows($userId) : [],
            'queues' => $this->queueRows($campaignId),
        ];
    }

    public function createPlaylist(array $auth, int $campaignId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $name = $this->name($payload['name'] ?? null);
        $this->requireCapacity(
            'audio_playlists',
            ['owner_user_id' => (int) $context['auth']['user_id']],
            self::MAX_PLAYLISTS,
            'playlist_limit_reached'
        );
        if (!$this->playlists->insert(['owner_user_id' => (int) $context['auth']['user_id'], 'name' => $name])) {
            throw new CampaignException('playlist_write_failed', 'Playlist could not be created.', 500);
        }
        return ['playlist' => $this->playlist((int) $this->playlists->getInsertID(), (int) $context['auth']['user_id'])];
    }

    public function renamePlaylist(array $auth, int $campaignId, int $playlistId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $playlist = $this->ownedPlaylist($playlistId, (int) $context['auth']['user_id']);
        if (!$this->playlists->update((int) $playlist['id'], ['name' => $this->name($payload['name'] ?? null)])) {
            throw new CampaignException('playlist_write_failed', 'Playlist could not be renamed.', 500);
        }
        return ['playlist' => $this->playlist($playlistId, (int) $context['auth']['user_id'])];
    }

    public function deletePlaylist(array $auth, int $campaignId, int $playlistId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $playlist = $this->ownedPlaylist($playlistId, (int) $context['auth']['user_id']);
        if (!$this->playlists->delete((int) $playlist['id'])) {
            throw new CampaignException('playlist_write_failed', 'Playlist could not be deleted.', 500);
        }
        return ['deleted' => true, 'playlistId' => $playlistId];
    }

    public function addPlaylistItem(array $auth, int $campaignId, int $playlistId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        $this->ownedPlaylist($playlistId, $userId);
        $this->requireCapacity(
            'audio_playlist_items',
            ['playlist_id' => $playlistId],
            self::MAX_PLAYLIST_ITEMS,
            'playlist_item_limit_reached'
        );
        $trackId = $this->positiveId($payload['trackId'] ?? null, 'audio_track_not_found');
        $this->requireTrack($context, $campaignId, $trackId, true);
        $position = $this->nextOrder('audio_playlist_items', ['playlist_id' => $playlistId]);
        if (!$this->playlistItems->insert([
            'playlist_id' => $playlistId, 'audio_track_id' => $trackId, 'sort_order' => $position,
        ])) {
            throw new CampaignException('playlist_write_failed', 'Track could not be added to playlist.', 500);
        }
        return ['playlist' => $this->playlist($playlistId, $userId)];
    }

    public function removePlaylistItem(array $auth, int $campaignId, int $playlistId, int $itemId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        $this->ownedPlaylist($playlistId, $userId);
        $item = $this->playlistItems->where('playlist_id', $playlistId)->find($itemId);
        if (!$item) {
            throw new CampaignException('playlist_item_not_found', 'Playlist item was not found.', 404);
        }
        $this->playlistItems->delete($itemId);
        $this->normalizeOrder('audio_playlist_items', ['playlist_id' => $playlistId]);
        return ['playlist' => $this->playlist($playlistId, $userId)];
    }

    public function movePlaylistItem(array $auth, int $campaignId, int $playlistId, int $itemId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        $this->ownedPlaylist($playlistId, $userId);
        $this->move('audio_playlist_items', ['playlist_id' => $playlistId], $itemId, $payload['position'] ?? null);
        return ['playlist' => $this->playlist($playlistId, $userId)];
    }

    public function addQueueTrack(array $auth, int $campaignId, string $channelId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $this->requireCapacity(
            'campaign_jukebox_queue_items',
            ['campaign_id' => $campaignId, 'channel_id' => $channelId],
            self::MAX_QUEUE_ITEMS,
            'queue_limit_reached'
        );
        $trackId = $this->positiveId($payload['trackId'] ?? null, 'audio_track_not_found');
        $this->requireTrack($context, $campaignId, $trackId, true);
        $this->insertQueue($campaignId, $channelId, $trackId, null, (int) $context['auth']['user_id']);
        return ['channelId' => $channelId, 'queue' => $this->queue($campaignId, $channelId)];
    }

    public function addQueuePlaylist(array $auth, int $campaignId, string $channelId, int $playlistId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $userId = (int) $context['auth']['user_id'];
        $playlist = $this->playlist($playlistId, $userId);
        $this->requireCapacity(
            'campaign_jukebox_queue_items',
            ['campaign_id' => $campaignId, 'channel_id' => $channelId],
            self::MAX_QUEUE_ITEMS,
            'queue_limit_reached',
            count($playlist['items'])
        );
        $this->db->transBegin();
        try {
            foreach ($playlist['items'] as $item) {
                $this->requireTrack($context, $campaignId, (int) $item['trackId'], true);
                $this->insertQueue($campaignId, $channelId, (int) $item['trackId'], $playlistId, $userId);
            }
            if ($this->db->transStatus() === false) {
                throw new CampaignException('queue_write_failed', 'Playlist could not be added to the queue.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['channelId' => $channelId, 'queue' => $this->queue($campaignId, $channelId)];
    }

    public function startPlaylist(array $auth, int $campaignId, string $channelId, int $playlistId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $userId = (int) $context['auth']['user_id'];
        $playlist = $this->playlist($playlistId, $userId);
        if (!$playlist['items']) {
            throw new CampaignException('playlist_empty', 'Playlist is empty.', 422);
        }
        foreach ($playlist['items'] as $item) {
            $this->requireTrack($context, $campaignId, (int) $item['trackId'], true);
        }
        $this->db->transBegin();
        try {
            $this->queueItems->where('campaign_id', $campaignId)->where('channel_id', $channelId)->delete();
            foreach (array_slice($playlist['items'], 1) as $item) {
                $this->insertQueue($campaignId, $channelId, (int) $item['trackId'], $playlistId, $userId);
            }
            if ($this->db->transStatus() === false) {
                throw new CampaignException('queue_write_failed', 'Channel queue could not be updated.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return [
            'channelId' => $channelId,
            'current' => $playlist['items'][0],
            'playlist' => ['id' => $playlist['id'], 'name' => $playlist['name']],
            'queue' => $this->queue($campaignId, $channelId),
        ];
    }

    public function removeQueueItem(array $auth, int $campaignId, string $channelId, int $itemId): array
    {
        $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $item = $this->queueItems->where('campaign_id', $campaignId)->where('channel_id', $channelId)->find($itemId);
        if (!$item) {
            throw new CampaignException('queue_item_not_found', 'Queue item was not found.', 404);
        }
        $this->queueItems->delete($itemId);
        $this->normalizeOrder('campaign_jukebox_queue_items', ['campaign_id' => $campaignId, 'channel_id' => $channelId]);
        return ['channelId' => $channelId, 'queue' => $this->queue($campaignId, $channelId)];
    }

    public function moveQueueItem(array $auth, int $campaignId, string $channelId, int $itemId, array $payload): array
    {
        $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $this->move('campaign_jukebox_queue_items', ['campaign_id' => $campaignId, 'channel_id' => $channelId], $itemId, $payload['position'] ?? null);
        return ['channelId' => $channelId, 'queue' => $this->queue($campaignId, $channelId)];
    }

    public function clearQueue(array $auth, int $campaignId, string $channelId): array
    {
        $this->requireGameMaster($auth, $campaignId);
        $channelId = $this->channel($channelId);
        $this->queueItems->where('campaign_id', $campaignId)->where('channel_id', $channelId)->delete();
        return ['channelId' => $channelId, 'queue' => []];
    }

    private function playlistRows(int $userId): array
    {
        $rows = $this->playlists->where('owner_user_id', $userId)->orderBy('name', 'ASC')->findAll();
        return array_map(fn (array $row): array => $this->presentPlaylist($row), $rows);
    }

    private function playlist(int $playlistId, int $userId): array
    {
        return $this->presentPlaylist($this->ownedPlaylist($playlistId, $userId));
    }

    private function presentPlaylist(array $row): array
    {
        $items = $this->trackQuery('audio_playlist_items items')
            ->select('items.id AS item_id, items.sort_order, items.playlist_id')
            ->where('items.playlist_id', (int) $row['id'])
            ->orderBy('items.sort_order', 'ASC')->orderBy('items.id', 'ASC')->get()->getResultArray();
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'items' => array_map(fn (array $item): array => $this->presentTrackItem($item), $items),
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function queueRows(int $campaignId): array
    {
        $queues = array_fill_keys(array_keys(JukeboxStateService::CHANNELS), []);
        $items = $this->trackQuery('campaign_jukebox_queue_items items')
            ->select('items.id AS item_id, items.sort_order, items.channel_id, items.playlist_id, playlists.name AS playlist_name')
            ->join('audio_playlists playlists', 'playlists.id = items.playlist_id', 'left')
            ->where('items.campaign_id', $campaignId)
            ->orderBy('items.channel_id', 'ASC')->orderBy('items.sort_order', 'ASC')->orderBy('items.id', 'ASC')
            ->get()->getResultArray();
        foreach ($items as $item) {
            if (array_key_exists((string) $item['channel_id'], $queues)) {
                $queues[(string) $item['channel_id']][] = $this->presentTrackItem($item);
            }
        }
        return $queues;
    }

    private function queue(int $campaignId, string $channelId): array
    {
        return $this->queueRows($campaignId)[$channelId] ?? [];
    }

    private function trackQuery(string $from)
    {
        return $this->db->table($from)
            ->select('tracks.id AS track_id, tracks.title, tracks.category, tracks.source_type, tracks.duration_seconds, tracks.loop_enabled')
            ->join('audio_tracks tracks', 'tracks.id = items.audio_track_id', 'inner')
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null);
    }

    private function presentTrackItem(array $row): array
    {
        return [
            'id' => (int) $row['item_id'],
            'trackId' => (int) $row['track_id'],
            'title' => (string) $row['title'],
            'category' => (string) $row['category'],
            'sourceType' => (string) $row['source_type'],
            'duration' => $row['duration_seconds'] === null ? null : (float) $row['duration_seconds'],
            'loop' => !empty($row['loop_enabled']),
            'playlistId' => empty($row['playlist_id']) ? null : (int) $row['playlist_id'],
            'playlistName' => (string) ($row['playlist_name'] ?? ''),
            'position' => (int) $row['sort_order'],
        ];
    }

    private function insertQueue(int $campaignId, string $channelId, int $trackId, ?int $playlistId, int $userId): void
    {
        $order = $this->nextOrder('campaign_jukebox_queue_items', ['campaign_id' => $campaignId, 'channel_id' => $channelId]);
        if (!$this->queueItems->insert([
            'campaign_id' => $campaignId, 'channel_id' => $channelId,
            'audio_track_id' => $trackId, 'playlist_id' => $playlistId,
            'added_by_user_id' => $userId, 'sort_order' => $order,
        ])) {
            throw new CampaignException('queue_write_failed', 'Track could not be added to the queue.', 500);
        }
    }

    private function requireTrack(array $context, int $campaignId, int $trackId, bool $attach): array
    {
        $row = $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.scope AS library_scope, libraries.system_id, libraries.setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'inner')
            ->where('tracks.id', $trackId)->where('tracks.status', 'ready')->where('tracks.deleted_at', null)
            ->where('libraries.is_active', 1)->get()->getRowArray();
        $campaign = $context['campaign'];
        $userId = (int) $context['auth']['user_id'];
        $system = $row && $row['library_scope'] === 'system'
            && (empty($row['system_id']) || (int) $row['system_id'] === (int) ($campaign['rpg_system_id'] ?? 0))
            && (empty($row['setting_id']) || (int) $row['setting_id'] === (int) ($campaign['rpg_universe_id'] ?? 0));
        $personal = $row && $row['library_scope'] === 'personal' && (int) ($row['owner_user_id'] ?? 0) === $userId;
        if (!$system && !$personal) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        if ($attach) {
            $link = $this->campaignTracks->where('campaign_id', $campaignId)->where('audio_track_id', $trackId)->first();
            if ($link) {
                $this->campaignTracks->update((int) $link['id'], ['is_enabled' => 1]);
            } elseif (!$this->campaignTracks->insert([
                'campaign_id' => $campaignId, 'audio_track_id' => $trackId,
                'added_by_user_id' => $userId, 'is_enabled' => 1, 'sort_order' => 0,
            ])) {
                throw new CampaignException('audio_track_write_failed', 'Audio track could not be attached.', 500);
            }
        }
        return $row;
    }

    private function ownedPlaylist(int $playlistId, int $userId): array
    {
        $row = $this->playlists->where('owner_user_id', $userId)->find($playlistId);
        if (!$row) {
            throw new CampaignException('playlist_not_found', 'Playlist was not found.', 404);
        }
        return $row;
    }

    private function requireGameMaster(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (!$this->isGameMaster($context)) {
            throw new CampaignException('forbidden', 'Only the campaign Game Master can manage the jukebox.', 403);
        }
        return $context;
    }

    private function isGameMaster(array $context): bool
    {
        return !empty($context['isGameMaster']) || !empty($context['isAdmin']);
    }

    private function channel($value): string
    {
        $channel = trim((string) $value);
        if (!array_key_exists($channel, JukeboxStateService::CHANNELS)) {
            throw new CampaignException('jukebox_channel_invalid', 'Jukebox channel is invalid.', 422);
        }
        return $channel;
    }

    private function name($value): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) > 180) {
            throw new CampaignException('playlist_name_invalid', 'Playlist name is invalid.', 422);
        }
        return $name;
    }

    private function positiveId($value, string $code): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException($code, 'Identifier is invalid.', 422);
        }
        return (int) $id;
    }

    private function nextOrder(string $table, array $scope): int
    {
        $builder = $this->db->table($table)->selectMax('sort_order');
        foreach ($scope as $field => $value) {
            $builder->where($field, $value);
        }
        $row = $builder->get()->getRowArray();
        return max(0, (int) ($row['sort_order'] ?? -1) + 1);
    }

    private function requireCapacity(
        string $table,
        array $scope,
        int $maximum,
        string $code,
        int $additional = 1
    ): void {
        $builder = $this->db->table($table);
        foreach ($scope as $field => $value) {
            $builder->where($field, $value);
        }
        if ($builder->countAllResults() + max(0, $additional) > $maximum) {
            throw new CampaignException($code, 'Jukebox collection limit was reached.', 422);
        }
    }

    private function move(string $table, array $scope, int $itemId, $position): void
    {
        $builder = $this->db->table($table);
        foreach ($scope as $field => $value) {
            $builder->where($field, $value);
        }
        $rows = $builder->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $index = array_search($itemId, $ids, true);
        if ($index === false) {
            throw new CampaignException('queue_item_not_found', 'Ordered item was not found.', 404);
        }
        $target = filter_var($position, FILTER_VALIDATE_INT);
        if ($target === false) {
            throw new CampaignException('position_invalid', 'Position is invalid.', 422);
        }
        array_splice($ids, $index, 1);
        array_splice($ids, max(0, min((int) $target, count($ids))), 0, [$itemId]);
        foreach ($ids as $order => $id) {
            $this->db->table($table)->where('id', $id)->update(['sort_order' => $order, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }

    private function normalizeOrder(string $table, array $scope): void
    {
        $builder = $this->db->table($table)->select('id');
        foreach ($scope as $field => $value) {
            $builder->where($field, $value);
        }
        foreach ($builder->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray() as $order => $row) {
            $this->db->table($table)->where('id', (int) $row['id'])->update(['sort_order' => $order, 'updated_at' => date('Y-m-d H:i:s')]);
        }
    }
}
