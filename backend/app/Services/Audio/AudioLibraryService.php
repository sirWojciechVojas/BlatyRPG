<?php

namespace App\Services\Audio;

use App\Models\AudioLibraryModel;
use App\Models\AudioTrackModel;
use App\Models\CampaignAudioTrackModel;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

final class AudioLibraryService
{
    private $db;
    private $guard;
    private $libraries;
    private $tracks;
    private $campaignTracks;
    private $storage;
    private $providers;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignGuardService $guard = null,
        ?AudioTrackStorage $storage = null,
        ?ExternalAudioProviderRegistry $providers = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->libraries = new AudioLibraryModel($this->db);
        $this->tracks = new AudioTrackModel($this->db);
        $this->campaignTracks = new CampaignAudioTrackModel($this->db);
        $this->storage = $storage ?: new AudioTrackStorage();
        $this->providers = $providers ?: new ExternalAudioProviderRegistry();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $campaignRows = $this->campaignRows($campaignId);
        $settingRows = $this->systemRows($context['campaign']);
        $personalRows = $this->isGameMaster($context)
            ? $this->personalRows((int) $context['auth']['user_id']) : [];
        $attached = array_fill_keys(array_map(
            static fn (array $row): int => (int) $row['id'],
            $campaignRows
        ), true);
        $rows = [];
        foreach (array_merge($campaignRows, $settingRows, $personalRows) as $row) {
            $rows[(int) $row['id']] = $row;
        }
        $sort = static function (array $left, array $right): int {
            return [$left['category'], mb_strtolower($left['title']), (int) $left['id']]
                <=> [$right['category'], mb_strtolower($right['title']), (int) $right['id']];
        };
        uasort($rows, $sort);
        usort($settingRows, $sort);
        usort($personalRows, $sort);
        $present = function (array $row) use ($campaignId, $attached): array {
            return $this->present($row, $campaignId, isset($attached[(int) $row['id']]));
        };
        $items = array_map($present, array_values($rows));
        $settingItems = array_map($present, $settingRows);
        $personalItems = array_map($present, $personalRows);
        $campaign = $context['campaign'];
        $system = !empty($campaign['rpg_system_id'])
            ? $this->db->table('rpg_systems')->select('id,code,name')
                ->where('id', (int) $campaign['rpg_system_id'])->get()->getRowArray() : null;
        $setting = !empty($campaign['rpg_universe_id'])
            ? $this->db->table('rpg_universes')->select('id,code,name')
                ->where('id', (int) $campaign['rpg_universe_id'])->get()->getRowArray() : null;
        $normalizeCatalog = static function (?array $row): ?array {
            return $row ? [
                'id' => (int) $row['id'],
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) $row['name'],
            ] : null;
        };
        return [
            'items' => $items,
            'count' => count($items),
            'libraries' => [
                'setting' => $settingItems,
                'personal' => $personalItems,
            ],
            'campaignCatalog' => [
                'system' => $normalizeCatalog($system),
                'setting' => $normalizeCatalog($setting),
            ],
            'categories' => ['music', 'ambient', 'sfx'],
            'capabilities' => ['canManage' => $this->isGameMaster($context)],
            'upload' => [
                'maxBytes' => $this->storage->limitBytes(),
                'extensions' => ['mp3', 'wav', 'ogg', 'oga', 'webm', 'm4a', 'mp4', 'aac', 'flac'],
            ],
        ];
    }

    public function upload(
        array $auth,
        int $campaignId,
        ?UploadedFile $file,
        array $payload
    ): array {
        $context = $this->requireGameMaster($auth, $campaignId);
        $metadata = $this->metadata($payload, $file ? pathinfo((string) $file->getClientName(), PATHINFO_FILENAME) : '');
        $stored = $this->storage->store($campaignId, $file);
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            try {
                $central = $this->media->uploadFile($auth, $stored['path'], [
                    'filename' => $stored['original_name'], 'mimeType' => $stored['mime_type'],
                    'category' => 'audio', 'visibility' => 'private',
                    'ownerUserId' => (int) $context['auth']['user_id'],
                ]);
            } catch (MediaException $exception) {
                $this->storage->remove($stored['storage_key']);
                throw new CampaignException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
        }
        $this->db->transBegin();
        try {
            $libraryId = $this->personalLibraryId((int) $context['auth']['user_id']);
            $track = $metadata + $stored + [
                'library_id' => $libraryId,
                'owner_user_id' => (int) $context['auth']['user_id'],
                'source_type' => 'upload',
                'status' => 'ready',
            ];
            unset($track['path']);
            if ($central) {
                $track['storage_key'] = null;
                $track['media_asset_id'] = (int) $central['id'];
            }
            if (!$this->tracks->insert($track)) {
                throw new CampaignException('audio_track_write_failed', 'Audio track could not be saved.', 500);
            }
            $trackId = (int) $this->tracks->getInsertID();
            $this->attach($campaignId, $trackId, (int) $context['auth']['user_id']);
            if ($this->db->transStatus() === false) {
                throw new CampaignException('audio_track_write_failed', 'Audio track could not be saved.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->storage->remove($stored['storage_key']);
            throw $exception;
        } finally {
            if ($central) $this->storage->remove($stored['storage_key']);
        }
        return ['track' => $this->present($this->trackRow($trackId), $campaignId)];
    }

    public function addExternal(array $auth, int $campaignId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $url = trim((string) ($payload['url'] ?? ''));
        $provider = $this->providers->resolve($url);
        $metadata = $this->metadata($payload, $provider['provider']);
        if (empty($metadata['thumbnail_url'])) {
            $metadata['thumbnail_url'] = $provider['thumbnailUrl'];
        }
        $userId = (int) $context['auth']['user_id'];
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            try {
                $central = $this->media->registerExternalUrl($auth, [
                    'sourceUrl' => $provider['url'],
                    'mediaType' => 'audio',
                    'category' => 'audio',
                    'name' => $metadata['title'],
                    'tags' => $metadata['tags_json'],
                    'ownerUserId' => $userId,
                ]);
            } catch (MediaException $exception) {
                throw new CampaignException(
                    $exception->errorCode(), $exception->getMessage(),
                    $exception->status(), $exception->errors()
                );
            }
        }
        $this->db->transBegin();
        try {
            $track = $metadata + [
                'library_id' => $this->personalLibraryId($userId),
                'owner_user_id' => $userId,
                'source_type' => 'external',
                'provider' => $provider['provider'],
                'provider_reference' => $provider['reference'],
                'external_url' => $provider['url'],
                'status' => 'ready',
            ];
            if ($central) {
                $track['media_asset_id'] = (int) $central['id'];
            }
            if (!$this->tracks->insert($track)) {
                throw new CampaignException('audio_track_write_failed', 'External track could not be saved.', 500);
            }
            $trackId = (int) $this->tracks->getInsertID();
            $this->attach($campaignId, $trackId, $userId);
            if ($this->db->transStatus() === false) {
                throw new CampaignException('audio_track_write_failed', 'External track could not be saved.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['track' => $this->present($this->trackRow($trackId), $campaignId)];
    }

    public function attachTrack(array $auth, int $campaignId, int $trackId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $row = $this->trackRow($trackId);
        $isSettingTrack = ($row['library_scope'] ?? null) === 'system'
            && $this->systemMatchesCampaign($row, $context['campaign']);
        $isOwnTrack = ($row['library_scope'] ?? null) === 'personal'
            && (int) ($row['owner_user_id'] ?? 0) === (int) $context['auth']['user_id'];
        if (!$isSettingTrack && !$isOwnTrack) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        $this->attach($campaignId, $trackId, (int) $context['auth']['user_id']);
        return ['track' => $this->present($this->trackRow($trackId), $campaignId, true)];
    }

    public function remove(array $auth, int $campaignId, int $trackId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $link = $this->campaignTracks->where('campaign_id', $campaignId)
            ->where('audio_track_id', $trackId)->first();
        if (!$link) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        $this->campaignTracks->delete((int) $link['id']);
        return ['removed' => true, 'trackId' => $trackId];
    }

    public function deletePersonalTrack(array $auth, int $campaignId, int $trackId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $row = $this->trackRow($trackId);
        if (($row['library_scope'] ?? null) !== 'personal'
            || (int) ($row['owner_user_id'] ?? 0) !== (int) $context['auth']['user_id']) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        $this->db->transBegin();
        try {
            $this->campaignTracks->where('audio_track_id', $trackId)->delete();
            if (!$this->tracks->delete($trackId) || $this->db->transStatus() === false) {
                throw new CampaignException('audio_track_write_failed', 'Audio track could not be deleted.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        if (!empty($row['storage_key'])) {
            $this->storage->remove((string) $row['storage_key']);
        }
        return ['deleted' => true, 'trackId' => $trackId];
    }

    public function updatePersonalTrack(
        array $auth,
        int $campaignId,
        int $trackId,
        array $payload
    ): array {
        $context = $this->requireGameMaster($auth, $campaignId);
        $row = $this->trackRow($trackId);
        if (($row['library_scope'] ?? null) !== 'personal'
            || (int) ($row['owner_user_id'] ?? 0) !== (int) $context['auth']['user_id']) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }

        $title = trim((string) ($payload['title'] ?? $row['title']));
        if ($title === '' || mb_strlen($title) > 180) {
            throw new CampaignException('validation_failed', 'Audio track title is invalid.', 422);
        }
        $data = ['title' => $title];
        if (array_key_exists('url', $payload)) {
            if (($row['source_type'] ?? null) !== 'external') {
                throw new CampaignException(
                    'validation_failed',
                    'Only external audio tracks can change their URL.',
                    422
                );
            }
            $provider = $this->providers->resolve(trim((string) $payload['url']));
            $data += [
                'provider' => $provider['provider'],
                'provider_reference' => $provider['reference'],
                'external_url' => $provider['url'],
                'thumbnail_url' => $provider['thumbnailUrl'],
                'duration_seconds' => null,
            ];
        }
        if (!$this->tracks->update($trackId, $data)) {
            throw new CampaignException(
                'audio_track_write_failed',
                'Audio track could not be updated.',
                500
            );
        }
        return ['track' => $this->present($this->trackRow($trackId), $campaignId)];
    }

    public function assetForPlayback(array $auth, int $campaignId, int $trackId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $row = $this->trackRow($trackId);
        if (!$this->isAvailable($row, $context['campaign'], $campaignId)
            || (empty($row['storage_key']) && empty($row['media_asset_id']))) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        if (!empty($row['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $row['media_asset_id']);
            } catch (MediaException $exception) {
                throw new CampaignException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['track' => $row, 'url' => $media['url']];
        }
        $path = $this->storage->path((string) $row['storage_key']);
        if (!is_file($path) || !is_readable($path)) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        return ['track' => $row, 'path' => $path];
    }

    private function campaignRows(int $campaignId): array
    {
        return $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.name AS library_name, libraries.scope AS library_scope, libraries.system_id AS library_system_id, libraries.setting_id AS library_setting_id')
            ->join('campaign_audio_tracks campaign_tracks', 'campaign_tracks.audio_track_id = tracks.id', 'inner')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->where('campaign_tracks.campaign_id', $campaignId)
            ->where('campaign_tracks.is_enabled', 1)
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null)
            ->orderBy('campaign_tracks.sort_order', 'ASC')->get()->getResultArray();
    }

    private function systemRows(array $campaign): array
    {
        $builder = $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.name AS library_name, libraries.scope AS library_scope, libraries.system_id AS library_system_id, libraries.setting_id AS library_setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'inner')
            ->where('libraries.scope', 'system')->where('libraries.is_active', 1)
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null);
        $systemId = (int) ($campaign['rpg_system_id'] ?? 0);
        $settingId = (int) ($campaign['rpg_universe_id'] ?? 0);
        $builder->groupStart()->where('libraries.system_id', null);
        if ($systemId > 0) {
            $builder->orWhere('libraries.system_id', $systemId);
        }
        $builder->groupEnd()->groupStart()->where('libraries.setting_id', null);
        if ($settingId > 0) {
            $builder->orWhere('libraries.setting_id', $settingId);
        }
        return $builder->groupEnd()->get()->getResultArray();
    }

    private function personalRows(int $userId): array
    {
        return $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.name AS library_name, libraries.scope AS library_scope, libraries.system_id AS library_system_id, libraries.setting_id AS library_setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'inner')
            ->where('libraries.scope', 'personal')->where('libraries.is_active', 1)
            ->where('libraries.owner_user_id', $userId)
            ->where('tracks.owner_user_id', $userId)
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null)
            ->get()->getResultArray();
    }

    private function metadata(array $payload, string $fallbackTitle): array
    {
        $title = trim((string) ($payload['title'] ?? $fallbackTitle));
        $category = strtolower(trim((string) ($payload['category'] ?? 'music')));
        if ($title === '' || mb_strlen($title) > 180 || !in_array($category, ['music', 'ambient', 'sfx'], true)) {
            throw new CampaignException('validation_failed', 'Audio metadata is invalid.', 422);
        }
        $duration = $payload['duration'] ?? $payload['durationSeconds'] ?? null;
        if ($duration !== null && (!is_numeric($duration) || (float) $duration < 0 || (float) $duration > 86400)) {
            throw new CampaignException('validation_failed', 'Audio duration is invalid.', 422);
        }
        $tags = $payload['tags'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : preg_split('/\s*,\s*/', $tags, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($tags) || count($tags) > 20) {
            throw new CampaignException('validation_failed', 'Audio tags are invalid.', 422);
        }
        $tags = array_values(array_unique(array_filter(array_map(static function ($tag): string {
            return mb_substr(trim((string) $tag), 0, 48);
        }, $tags))));
        $thumbnail = trim((string) ($payload['thumbnail'] ?? $payload['thumbnailUrl'] ?? ''));
        if (strlen($thumbnail) > 2048 || ($thumbnail !== '' && !preg_match('#^(https://|/)#i', $thumbnail))) {
            throw new CampaignException('validation_failed', 'Thumbnail URL is invalid.', 422);
        }
        return [
            'title' => $title,
            'category' => $category,
            'duration_seconds' => $duration === null ? null : round((float) $duration, 3),
            'loop_enabled' => filter_var($payload['loop'] ?? false, FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
            'tags_json' => $tags,
            'thumbnail_url' => $thumbnail ?: null,
        ];
    }

    private function personalLibraryId(int $userId): int
    {
        $row = $this->libraries->where('scope', 'personal')->where('owner_user_id', $userId)->first();
        if ($row) {
            return (int) $row['id'];
        }
        if (!$this->libraries->insert([
            'name' => 'Game Master Library', 'scope' => 'personal',
            'owner_user_id' => $userId, 'is_active' => 1,
        ])) {
            throw new CampaignException('audio_library_write_failed', 'Audio library could not be created.', 500);
        }
        return (int) $this->libraries->getInsertID();
    }

    private function attach(int $campaignId, int $trackId, int $userId): void
    {
        $existing = $this->campaignTracks->where('campaign_id', $campaignId)
            ->where('audio_track_id', $trackId)->first();
        if ($existing) {
            $this->campaignTracks->update((int) $existing['id'], ['is_enabled' => 1]);
            return;
        }
        if (!$this->campaignTracks->insert([
            'campaign_id' => $campaignId, 'audio_track_id' => $trackId,
            'added_by_user_id' => $userId, 'is_enabled' => 1, 'sort_order' => 0,
        ])) {
            throw new CampaignException('audio_track_write_failed', 'Audio track could not be attached.', 500);
        }
    }

    private function trackRow(int $trackId): array
    {
        $row = $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.name AS library_name, libraries.scope AS library_scope, libraries.system_id AS library_system_id, libraries.setting_id AS library_setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->where('tracks.id', $trackId)->where('tracks.deleted_at', null)->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        return $row;
    }

    private function present(array $row, int $campaignId, ?bool $attached = null): array
    {
        $sourceType = (string) $row['source_type'];
        $id = (int) $row['id'];
        $tags = $row['tags_json'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => $id,
            'title' => (string) $row['title'],
            'category' => (string) $row['category'],
            'systemId' => !empty($row['library_system_id']) ? (int) $row['library_system_id'] : null,
            'settingId' => !empty($row['library_setting_id']) ? (int) $row['library_setting_id'] : null,
            'sourceType' => $sourceType,
            'provider' => $row['provider'] ?? null,
            'providerReference' => $row['provider_reference'] ?? null,
            'url' => (!empty($row['storage_key']) || !empty($row['media_asset_id']))
                ? '/api/campaigns/' . $campaignId . '/audio/tracks/' . $id . '/file'
                : ($row['external_url'] ?? null),
            'duration' => $row['duration_seconds'] === null ? null : (float) $row['duration_seconds'],
            'loop' => !empty($row['loop_enabled']),
            'tags' => is_array($tags) ? $tags : [],
            'thumbnail' => $row['thumbnail_url'] ?? null,
            'mimeType' => $row['mime_type'] ?? null,
            'fileSize' => !empty($row['byte_size']) ? (int) $row['byte_size'] : null,
            'attached' => $attached ?? $this->campaignTracks
                ->where('campaign_id', $campaignId)->where('audio_track_id', $id)
                ->where('is_enabled', 1)->countAllResults() > 0,
            'library' => [
                'id' => !empty($row['library_id']) ? (int) $row['library_id'] : null,
                'name' => (string) ($row['library_name'] ?? ''),
                'scope' => (string) ($row['library_scope'] ?? ''),
            ],
        ];
    }

    private function requireGameMaster(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (!$this->isGameMaster($context)) {
            throw new CampaignException('forbidden', 'Only the campaign Game Master can manage audio.', 403);
        }
        return $context;
    }

    private function isGameMaster(array $context): bool
    {
        return !empty($context['isGameMaster']) || !empty($context['isAdmin']);
    }

    private function isAvailable(array $row, array $campaign, int $campaignId): bool
    {
        if (($row['library_scope'] ?? null) === 'system' && $this->systemMatchesCampaign($row, $campaign)) {
            return true;
        }
        return $this->campaignTracks->where('campaign_id', $campaignId)
            ->where('audio_track_id', (int) $row['id'])->where('is_enabled', 1)->countAllResults() > 0;
    }

    private function systemMatchesCampaign(array $row, array $campaign): bool
    {
        $systemId = (int) ($row['library_system_id'] ?? 0);
        $settingId = (int) ($row['library_setting_id'] ?? 0);
        return ($systemId === 0 || $systemId === (int) ($campaign['rpg_system_id'] ?? 0))
            && ($settingId === 0 || $settingId === (int) ($campaign['rpg_universe_id'] ?? 0));
    }
}
