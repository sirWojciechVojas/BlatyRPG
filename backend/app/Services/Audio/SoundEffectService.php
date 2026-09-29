<?php

namespace App\Services\Audio;

use App\Models\CampaignAudioTrackModel;
use App\Models\CampaignSoundEffectSettingModel;
use App\Models\SoundEffectPlaybackModel;
use App\Models\SoundEffectScreenModel;
use App\Models\SoundEffectSlotModel;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

final class SoundEffectService
{
    private const DEFAULT_SCREEN_COUNT = 4;
    private const DEFAULT_GRID_COLUMNS = 3;
    private const DEFAULT_GRID_ROWS = 4;
    private const DEFAULT_TEXT_LINES = 1;
    private const MAX_GRID_COLUMNS = 20;
    private const MAX_GRID_ROWS = 10;
    private const MAX_TEXT_LINES = 5;
    private const PAD_STYLES = ['square', 'wide', 'compact'];

    private $db;
    private $guard;
    private $screens;
    private $slots;
    private $playbacks;
    private $settings;
    private $campaignTracks;

    public function __construct(?BaseConnection $db = null, ?CampaignGuardService $guard = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->screens = new SoundEffectScreenModel($this->db);
        $this->slots = new SoundEffectSlotModel($this->db);
        $this->playbacks = new SoundEffectPlaybackModel($this->db);
        $this->settings = new CampaignSoundEffectSettingModel($this->db);
        $this->campaignTracks = new CampaignAudioTrackModel($this->db);
    }

    public function snapshot(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $defaultAuthorId = (int) ($context['campaign']['game_master_id'] ?? $context['auth']['user_id']);
        $this->ensureDefaults($campaignId, $defaultAuthorId);
        $settings = $this->settings->find($campaignId);
        $screens = $this->screenRows($campaignId);
        $slotRows = $this->slotRows($campaignId);
        $slotsByScreen = [];
        foreach ($slotRows as $row) {
            $slotsByScreen[(int) $row['screen_id']][] = $this->presentSlot($row, $campaignId);
        }
        $presentedScreens = array_map(static function (array $row) use ($slotsByScreen): array {
            $id = (int) $row['id'];
            return [
                'id' => $id,
                'campaignId' => (int) $row['campaign_id'],
                'label' => (string) $row['label'],
                'name' => (string) ($row['name'] ?? ''),
                'sortOrder' => (int) $row['sort_order'],
                'columns' => (int) ($row['grid_columns'] ?? self::DEFAULT_GRID_COLUMNS),
                'rows' => (int) ($row['grid_rows'] ?? self::DEFAULT_GRID_ROWS),
                'textLines' => (int) ($row['text_lines'] ?? self::DEFAULT_TEXT_LINES),
                'padStyle' => (string) ($row['pad_style'] ?? 'square'),
                'slots' => $slotsByScreen[$id] ?? [],
                'createdAt' => $row['created_at'] ?? null,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }, $screens);

        return [
            'campaignId' => $campaignId,
            'screens' => $presentedScreens,
            'activePlaybacks' => $this->activePlaybacks($context, $campaignId),
            'revision' => (int) ($settings['revision'] ?? 1),
            'capabilities' => [
                'canManage' => $this->isGameMaster($context),
                'canControl' => $this->isGameMaster($context),
            ],
        ];
    }

    public function createScreen(array $auth, int $campaignId, array $payload = []): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        if (array_diff(array_keys($payload), ['name', 'columns', 'rows', 'textLines', 'padStyle'])) {
            throw new CampaignException('validation_failed', 'Sound effect screen payload is invalid.', 422);
        }
        $this->ensureDefaults($campaignId, (int) $context['auth']['user_id']);
        $rows = $this->screenRows($campaignId);
        $nextIndex = -1;
        foreach ($rows as $row) {
            $nextIndex = max($nextIndex, SoundEffectScreenLabel::toIndex((string) $row['label']));
        }
        $name = $this->screenName($payload['name'] ?? '');
        $layout = $this->screenLayout([], $payload);
        $userId = (int) $context['auth']['user_id'];
        if (!$this->screens->insert([
            'campaign_id' => $campaignId,
            'label' => SoundEffectScreenLabel::fromIndex($nextIndex + 1),
            'name' => $name ?: null,
            'sort_order' => count($rows),
            ...$layout,
            'created_by_user_id' => $userId,
            'updated_by_user_id' => $userId,
        ])) {
            throw new CampaignException('sound_effect_screen_write_failed', 'Sound effect screen could not be created.', 500);
        }
        $screenId = (int) $this->screens->getInsertID();
        $this->bumpRevision($campaignId, $userId);
        return ['screen' => $this->presentScreen($this->screen($campaignId, $screenId)), 'revision' => $this->revision($campaignId)];
    }

    public function updateScreen(array $auth, int $campaignId, int $screenId, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $screen = $this->screen($campaignId, $screenId);
        $allowed = ['name', 'position', 'columns', 'rows', 'textLines', 'padStyle'];
        if (array_diff(array_keys($payload), $allowed) || !$payload) {
            throw new CampaignException('validation_failed', 'Sound effect screen payload is invalid.', 422);
        }
        $userId = (int) $context['auth']['user_id'];
        $updates = [];
        if (array_key_exists('name', $payload)) {
            $updates['name'] = $this->screenName($payload['name']) ?: null;
        }
        if (array_intersect(['columns', 'rows', 'textLines', 'padStyle'], array_keys($payload))) {
            $updates = array_merge($updates, $this->screenLayout($screen, $payload));
        }
        if ($updates) {
            $updates['updated_by_user_id'] = $userId;
            if (!$this->screens->update($screenId, $updates)) {
                throw new CampaignException('sound_effect_screen_write_failed', 'Sound effect screen could not be updated.', 500);
            }
        }
        if (array_key_exists('position', $payload)) {
            $this->moveScreen($campaignId, $screenId, $payload['position'], $userId);
        }
        $this->bumpRevision($campaignId, $userId);
        return ['screen' => $this->presentScreen($this->screen($campaignId, $screenId)), 'revision' => $this->revision($campaignId)];
    }

    public function duplicateScreen(array $auth, int $campaignId, int $screenId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $source = $this->screen($campaignId, $screenId);
        $created = $this->createScreen($auth, $campaignId, [
            'name' => trim((string) ($source['name'] ?? '')) !== '' ? trim((string) $source['name']) . ' (kopia)' : '',
            'columns' => (int) ($source['grid_columns'] ?? self::DEFAULT_GRID_COLUMNS),
            'rows' => (int) ($source['grid_rows'] ?? self::DEFAULT_GRID_ROWS),
            'textLines' => (int) ($source['text_lines'] ?? self::DEFAULT_TEXT_LINES),
            'padStyle' => (string) ($source['pad_style'] ?? 'square'),
        ]);
        $targetId = (int) $created['screen']['id'];
        $userId = (int) $context['auth']['user_id'];
        foreach ($this->slots->where('screen_id', $screenId)->findAll() as $slot) {
            unset($slot['id'], $slot['created_at'], $slot['updated_at']);
            $slot['screen_id'] = $targetId;
            $slot['created_by_user_id'] = $userId;
            $slot['updated_by_user_id'] = $userId;
            $this->slots->insert($slot);
        }
        $this->bumpRevision($campaignId, $userId);
        return ['screen' => $this->presentScreen($this->screen($campaignId, $targetId), true), 'revision' => $this->revision($campaignId)];
    }

    public function deleteScreen(array $auth, int $campaignId, int $screenId): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $this->screen($campaignId, $screenId);
        $rows = $this->screenRows($campaignId);
        if (count($rows) <= 1) {
            throw new CampaignException('sound_effect_last_screen', 'The last sound effect screen cannot be deleted.', 409);
        }
        $this->screens->delete($screenId);
        $userId = (int) $context['auth']['user_id'];
        $this->normalizeScreenOrder($campaignId, $userId);
        $this->bumpRevision($campaignId, $userId);
        return ['deleted' => true, 'screenId' => $screenId, 'revision' => $this->revision($campaignId)];
    }

    public function saveSlot(array $auth, int $campaignId, int $screenId, int $position, array $payload): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $allowed = [
            'audioTrackId', 'name', 'icon', 'color', 'shortcut', 'volume',
            'playMode', 'loop', 'fadeInMs', 'fadeOutMs', 'stopOthers',
            'audienceScope', 'recipientUserIds',
        ];
        if (array_diff(array_keys($payload), $allowed)) {
            throw new CampaignException('validation_failed', 'Sound effect slot payload is invalid.', 422);
        }
        $screen = $this->screen($campaignId, $screenId);
        if ($position < 0 || $position >= $this->screenCapacity($screen)) {
            throw new CampaignException('validation_failed', 'Sound effect slot position is invalid.', 422);
        }
        $trackId = $this->positiveId($payload['audioTrackId'] ?? null, 'audio_track_not_found');
        $track = $this->manageableTrack($context, $campaignId, $trackId);
        $userId = (int) $context['auth']['user_id'];
        $existing = $this->slots->where('screen_id', $screenId)->where('slot_position', $position)->first();
        $recipients = $this->recipients($campaignId, $payload['audienceScope'] ?? 'all', $payload['recipientUserIds'] ?? []);
        $shortcut = $this->shortcut($payload['shortcut'] ?? null);
        if ($shortcut !== null) {
            $duplicate = $this->slots->where('campaign_id', $campaignId)->where('shortcut', $shortcut);
            if ($existing) {
                $duplicate->where('id !=', (int) $existing['id']);
            }
            if ($duplicate->first()) {
                throw new CampaignException('sound_effect_shortcut_conflict', 'The keyboard shortcut is already assigned.', 409);
            }
        }
        $playMode = strtolower(trim((string) ($payload['playMode'] ?? 'once')));
        if (!in_array($playMode, ['once', 'loop'], true)) {
            throw new CampaignException('validation_failed', 'Sound effect play mode is invalid.', 422);
        }
        foreach (['loop', 'stopOthers'] as $booleanField) {
            if (array_key_exists($booleanField, $payload) && !is_bool($payload[$booleanField])) {
                throw new CampaignException('validation_failed', 'Sound effect boolean setting is invalid.', 422);
            }
        }
        $loop = !empty($payload['loop']) || $playMode === 'loop';
        $data = [
            'campaign_id' => $campaignId,
            'screen_id' => $screenId,
            'slot_position' => $position,
            'audio_track_id' => $trackId,
            'name' => $this->limitedText($payload['name'] ?? $track['title'], 80, true),
            'icon' => $this->icon($payload['icon'] ?? 'waveform'),
            'color' => $this->color($payload['color'] ?? '#b98a45'),
            'shortcut' => $shortcut,
            'volume' => $this->number($payload['volume'] ?? 1, 0, 1),
            'play_mode' => $loop ? 'loop' : 'once',
            'loop_enabled' => $loop ? 1 : 0,
            'fade_in_ms' => (int) $this->number($payload['fadeInMs'] ?? 0, 0, 60000),
            'fade_out_ms' => (int) $this->number($payload['fadeOutMs'] ?? 0, 0, 60000),
            'stop_others' => !empty($payload['stopOthers']) ? 1 : 0,
            'audience_scope' => $recipients['scope'],
            'recipient_user_ids_json' => $recipients['ids'],
            'updated_by_user_id' => $userId,
        ];
        $this->attachTrack($campaignId, $trackId, $userId);
        if ($existing) {
            $ok = $this->slots->update((int) $existing['id'], $data);
            $slotId = (int) $existing['id'];
        } else {
            $data['created_by_user_id'] = $userId;
            $ok = $this->slots->insert($data);
            $slotId = (int) $this->slots->getInsertID();
        }
        if (!$ok) {
            throw new CampaignException('sound_effect_slot_write_failed', 'Sound effect slot could not be saved.', 500);
        }
        $this->bumpRevision($campaignId, $userId);
        return ['slot' => $this->presentSlot($this->slotRow($campaignId, $slotId), $campaignId), 'revision' => $this->revision($campaignId)];
    }

    public function deleteSlot(array $auth, int $campaignId, int $screenId, int $position): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $this->screen($campaignId, $screenId);
        $slot = $this->slots->where('screen_id', $screenId)->where('slot_position', $position)->first();
        if (!$slot) {
            throw new CampaignException('sound_effect_slot_not_found', 'Sound effect slot was not found.', 404);
        }
        $this->slots->delete((int) $slot['id']);
        $this->bumpRevision($campaignId, (int) $context['auth']['user_id']);
        return ['deleted' => true, 'screenId' => $screenId, 'position' => $position, 'revision' => $this->revision($campaignId)];
    }

    /** Called only through the authenticated internal realtime adapter. */
    public function command(array $auth, int $campaignId, array $request): array
    {
        $context = $this->requireGameMaster($auth, $campaignId);
        $type = strtoupper((string) ($request['type'] ?? ''));
        if ($type === 'SOUND_EFFECT_PLAY') {
            return $this->play($context, $campaignId, $request);
        }
        if ($type === 'SOUND_EFFECT_STOP') {
            return $this->stop($context, $campaignId, $request);
        }
        if ($type === 'SOUND_EFFECT_STOP_ALL') {
            return $this->stopAll($context, $campaignId, $request);
        }
        if ($type === 'SOUND_EFFECT_SETTINGS') {
            return [
                'type' => $type,
                'requestId' => (string) ($request['requestId'] ?? ''),
                'revision' => $this->revision($campaignId),
                'serverTime' => $this->milliseconds(),
                'audienceScope' => 'all',
                'recipientUserIds' => [],
            ];
        }
        throw new CampaignException('validation_failed', 'Sound effect command is invalid.', 422);
    }

    public function realtimeState(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        return [
            'activePlaybacks' => $this->activePlaybacks($context, $campaignId),
            'revision' => $this->revision($campaignId),
            'serverTime' => $this->milliseconds(),
        ];
    }

    private function play(array $context, int $campaignId, array $request): array
    {
        $playbackId = trim((string) ($request['playbackId'] ?? ''));
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $playbackId)) {
            throw new CampaignException('validation_failed', 'Playback id is invalid.', 422);
        }
        $slotId = $this->positiveId($request['slotId'] ?? null, 'sound_effect_slot_not_found');
        $slot = $this->slotRow($campaignId, $slotId);
        $track = $this->trackRow((int) $slot['audio_track_id']);
        if (!$this->trackAvailable($context['campaign'], $campaignId, $track)) {
            throw new CampaignException('audio_track_not_found', 'Audio track is not available in this campaign.', 404);
        }
        $now = $this->milliseconds();
        $executeAt = $now + 300;
        if (!empty($slot['stop_others'])) {
            $this->db->table('sound_effect_playbacks')->where('campaign_id', $campaignId)
                ->where('stopped_at_ms', null)->update(['stopped_at_ms' => $now, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $row = [
            'playback_id' => $playbackId,
            'campaign_id' => $campaignId,
            'slot_id' => $slotId,
            'audio_track_id' => (int) $track['id'],
            'started_at_ms' => $executeAt,
            'execute_at_ms' => $executeAt,
            'duration_seconds' => $track['duration_seconds'] === null ? null : (float) $track['duration_seconds'],
            'volume' => (float) $slot['volume'],
            'loop_enabled' => !empty($slot['loop_enabled']) ? 1 : 0,
            'fade_in_ms' => (int) $slot['fade_in_ms'],
            'fade_out_ms' => (int) $slot['fade_out_ms'],
            'audience_scope' => (string) $slot['audience_scope'],
            'recipient_user_ids_json' => $this->jsonArray($slot['recipient_user_ids_json'] ?? []),
            'created_by_user_id' => (int) $context['auth']['user_id'],
        ];
        if (!$this->playbacks->insert($row)) {
            throw new CampaignException('sound_effect_playback_failed', 'Sound effect playback could not be started.', 500);
        }
        return $this->presentPlayback($row + ['track' => $track], $campaignId, 'SOUND_EFFECT_PLAY', (string) ($request['requestId'] ?? ''), $now)
            + ['stopOthers' => !empty($slot['stop_others'])];
    }

    private function stop(array $context, int $campaignId, array $request): array
    {
        $playbackId = trim((string) ($request['playbackId'] ?? ''));
        $row = $this->playbackRow($campaignId, $playbackId);
        $now = $this->milliseconds();
        $this->playbacks->update($playbackId, ['stopped_at_ms' => $now]);
        return $this->presentPlayback($row, $campaignId, 'SOUND_EFFECT_STOP', (string) ($request['requestId'] ?? ''), $now);
    }

    private function stopAll(array $context, int $campaignId, array $request): array
    {
        $now = $this->milliseconds();
        $this->db->table('sound_effect_playbacks')->where('campaign_id', $campaignId)
            ->where('stopped_at_ms', null)->update(['stopped_at_ms' => $now, 'updated_at' => date('Y-m-d H:i:s')]);
        return [
            'type' => 'SOUND_EFFECT_STOP_ALL',
            'requestId' => (string) ($request['requestId'] ?? ''),
            'serverTime' => $now,
            'executeAt' => $now + 100,
            'fadeOutMs' => max(0, min(60000, (int) ($request['fadeOutMs'] ?? 0))),
            'audienceScope' => 'all',
            'recipientUserIds' => [],
        ];
    }

    private function activePlaybacks(array $context, int $campaignId): array
    {
        $now = $this->milliseconds();
        $builder = $this->db->table('sound_effect_playbacks playbacks')
            ->select('playbacks.*, tracks.title, tracks.category, tracks.source_type, tracks.external_url, tracks.storage_key, tracks.mime_type')
            ->join('audio_tracks tracks', 'tracks.id = playbacks.audio_track_id', 'inner')
            ->where('playbacks.campaign_id', $campaignId)->where('playbacks.stopped_at_ms', null)
            ->where('tracks.deleted_at', null);
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('tracks.media_asset_id');
        }
        $rows = $builder->get()->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $duration = $row['duration_seconds'] === null ? null : (float) $row['duration_seconds'];
            $expiresAt = (int) $row['started_at_ms'] + (int) ceil(($duration ?? 300.0) * 1000);
            if (empty($row['loop_enabled']) && $now > $expiresAt) {
                $this->playbacks->update((string) $row['playback_id'], ['stopped_at_ms' => $now]);
                continue;
            }
            if (!$this->audienceIncludes($context, $row)) {
                continue;
            }
            $result[] = $this->presentPlayback($row, $campaignId, 'SOUND_EFFECT_PLAY', '', $now);
        }
        return $result;
    }

    private function audienceIncludes(array $context, array $row): bool
    {
        $scope = (string) ($row['audience_scope'] ?? 'all');
        if ($scope === 'all') {
            return true;
        }
        if ($scope === 'gm') {
            return $this->isGameMaster($context);
        }
        return in_array((int) $context['auth']['user_id'], $this->jsonArray($row['recipient_user_ids_json'] ?? []), true)
            || (int) $context['auth']['user_id'] === (int) ($row['created_by_user_id'] ?? 0);
    }

    private function presentPlayback(array $row, int $campaignId, string $type, string $requestId, int $serverTime): array
    {
        $track = isset($row['track']) && is_array($row['track']) ? $row['track'] : $row;
        $trackId = (int) $row['audio_track_id'];
        return [
            'type' => $type,
            'requestId' => $requestId,
            'playbackId' => (string) $row['playback_id'],
            'campaignId' => $campaignId,
            'slotId' => !empty($row['slot_id']) ? (int) $row['slot_id'] : null,
            'audio' => [
                'id' => $trackId,
                'title' => (string) ($track['title'] ?? ''),
                'category' => (string) ($track['category'] ?? 'sfx'),
                'sourceType' => (string) ($track['source_type'] ?? ''),
                'url' => (!empty($track['storage_key']) || !empty($track['media_asset_id']))
                    ? '/api/campaigns/' . $campaignId . '/audio/tracks/' . $trackId . '/file'
                    : ($track['external_url'] ?? null),
                'mimeType' => $track['mime_type'] ?? null,
                'duration' => $row['duration_seconds'] === null ? null : (float) $row['duration_seconds'],
            ],
            'serverTime' => $serverTime,
            'startedAt' => (int) $row['started_at_ms'],
            'executeAt' => (int) $row['execute_at_ms'],
            'volume' => (float) $row['volume'],
            'loop' => !empty($row['loop_enabled']),
            'fadeInMs' => (int) $row['fade_in_ms'],
            'fadeOutMs' => (int) $row['fade_out_ms'],
            'audienceScope' => (string) $row['audience_scope'],
            'recipientUserIds' => $this->jsonArray($row['recipient_user_ids_json'] ?? []),
        ];
    }

    private function ensureDefaults(int $campaignId, int $userId): void
    {
        if ($this->screens->where('campaign_id', $campaignId)->countAllResults() > 0) {
            if (!$this->settings->find($campaignId)) {
                $this->settings->insert(['campaign_id' => $campaignId, 'revision' => 1, 'updated_by_user_id' => $userId]);
            }
            return;
        }
        $this->db->transBegin();
        try {
            if (!$this->settings->find($campaignId)) {
                $this->settings->insert(['campaign_id' => $campaignId, 'revision' => 1, 'updated_by_user_id' => $userId]);
            }
            if ($this->screens->where('campaign_id', $campaignId)->countAllResults() === 0) {
                for ($index = 0; $index < self::DEFAULT_SCREEN_COUNT; $index++) {
                    $this->screens->insert([
                        'campaign_id' => $campaignId,
                        'label' => SoundEffectScreenLabel::fromIndex($index),
                        'name' => null,
                        'sort_order' => $index,
                        'created_by_user_id' => $userId,
                        'updated_by_user_id' => $userId,
                    ]);
                }
            }
            if ($this->db->transStatus() === false) {
                throw new CampaignException('sound_effect_initialization_failed', 'Sound effect screens could not be initialized.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function screenRows(int $campaignId): array
    {
        return $this->screens->where('campaign_id', $campaignId)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
    }

    private function slotRows(int $campaignId): array
    {
        $builder = $this->db->table('sound_effect_slots slots')
            ->select('slots.*, tracks.title, tracks.category, tracks.source_type, tracks.external_url, tracks.storage_key, tracks.mime_type, tracks.duration_seconds, tracks.status AS track_status, tracks.deleted_at AS track_deleted_at, libraries.scope AS library_scope, libraries.name AS library_name')
            ->join('audio_tracks tracks', 'tracks.id = slots.audio_track_id', 'inner')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->where('slots.campaign_id', $campaignId)
            ->orderBy('slots.screen_id', 'ASC')->orderBy('slots.slot_position', 'ASC');
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('tracks.media_asset_id');
        }
        return $builder->get()->getResultArray();
    }

    private function slotRow(int $campaignId, int $slotId): array
    {
        $builder = $this->db->table('sound_effect_slots slots')
            ->select('slots.*, tracks.title, tracks.category, tracks.source_type, tracks.external_url, tracks.storage_key, tracks.mime_type, tracks.duration_seconds, tracks.status AS track_status, tracks.deleted_at AS track_deleted_at, libraries.scope AS library_scope, libraries.name AS library_name')
            ->join('audio_tracks tracks', 'tracks.id = slots.audio_track_id', 'inner')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->where('slots.campaign_id', $campaignId)->where('slots.id', $slotId);
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('tracks.media_asset_id');
        }
        $rows = $builder->get()->getRowArray();
        if (!$rows) {
            throw new CampaignException('sound_effect_slot_not_found', 'Sound effect slot was not found.', 404);
        }
        return $rows;
    }

    private function presentSlot(array $row, int $campaignId): array
    {
        $trackId = (int) $row['audio_track_id'];
        return [
            'id' => (int) $row['id'],
            'screenId' => (int) $row['screen_id'],
            'position' => (int) $row['slot_position'],
            'audioTrackId' => $trackId,
            'name' => (string) $row['name'],
            'icon' => (string) $row['icon'],
            'color' => (string) $row['color'],
            'shortcut' => $row['shortcut'] ?: null,
            'volume' => (float) $row['volume'],
            'playMode' => (string) $row['play_mode'],
            'loop' => !empty($row['loop_enabled']),
            'fadeInMs' => (int) $row['fade_in_ms'],
            'fadeOutMs' => (int) $row['fade_out_ms'],
            'stopOthers' => !empty($row['stop_others']),
            'audienceScope' => (string) $row['audience_scope'],
            'recipientUserIds' => $this->jsonArray($row['recipient_user_ids_json'] ?? []),
            'audio' => [
                'id' => $trackId,
                'title' => (string) $row['title'],
                'category' => (string) $row['category'],
                'sourceType' => (string) $row['source_type'],
                'url' => (!empty($row['storage_key']) || !empty($row['media_asset_id']))
                    ? '/api/campaigns/' . $campaignId . '/audio/tracks/' . $trackId . '/file'
                    : ($row['external_url'] ?? null),
                'duration' => $row['duration_seconds'] === null ? null : (float) $row['duration_seconds'],
                'mimeType' => $row['mime_type'] ?? null,
                'library' => ['scope' => (string) ($row['library_scope'] ?? ''), 'name' => (string) ($row['library_name'] ?? '')],
                'available' => ($row['track_status'] ?? '') === 'ready' && empty($row['track_deleted_at']),
            ],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }

    private function presentScreen(array $row, bool $withSlots = false): array
    {
        $presented = [
            'id' => (int) $row['id'], 'campaignId' => (int) $row['campaign_id'],
            'label' => (string) $row['label'], 'name' => (string) ($row['name'] ?? ''),
            'sortOrder' => (int) $row['sort_order'],
            'columns' => (int) ($row['grid_columns'] ?? self::DEFAULT_GRID_COLUMNS),
            'rows' => (int) ($row['grid_rows'] ?? self::DEFAULT_GRID_ROWS),
            'textLines' => (int) ($row['text_lines'] ?? self::DEFAULT_TEXT_LINES),
            'padStyle' => (string) ($row['pad_style'] ?? 'square'),
            'slots' => [],
            'createdAt' => $row['created_at'] ?? null, 'updatedAt' => $row['updated_at'] ?? null,
        ];
        if ($withSlots) {
            $presented['slots'] = array_map(fn (array $slot): array => $this->presentSlot($this->slotRow((int) $row['campaign_id'], (int) $slot['id']), (int) $row['campaign_id']), $this->slots->where('screen_id', (int) $row['id'])->findAll());
        }
        return $presented;
    }

    private function screen(int $campaignId, int $screenId): array
    {
        $screen = $this->screens->where('campaign_id', $campaignId)->where('id', $screenId)->first();
        if (!$screen) {
            throw new CampaignException('sound_effect_screen_not_found', 'Sound effect screen was not found.', 404);
        }
        return $screen;
    }

    private function moveScreen(int $campaignId, int $screenId, $position, int $userId): void
    {
        if (filter_var($position, FILTER_VALIDATE_INT) === false) {
            throw new CampaignException('validation_failed', 'Screen position is invalid.', 422);
        }
        $rows = $this->screenRows($campaignId);
        $ids = array_values(array_filter(array_map(static fn (array $row): int => (int) $row['id'], $rows), static fn (int $id): bool => $id !== $screenId));
        $target = max(0, min(count($ids), (int) $position));
        array_splice($ids, $target, 0, [$screenId]);
        foreach ($ids as $order => $id) {
            $this->screens->update($id, ['sort_order' => $order, 'updated_by_user_id' => $userId]);
        }
    }

    private function normalizeScreenOrder(int $campaignId, int $userId): void
    {
        foreach ($this->screenRows($campaignId) as $order => $row) {
            $this->screens->update((int) $row['id'], ['sort_order' => $order, 'updated_by_user_id' => $userId]);
        }
    }

    private function manageableTrack(array $context, int $campaignId, int $trackId): array
    {
        $track = $this->trackRow($trackId);
        if ($this->trackAvailable($context['campaign'], $campaignId, $track)) {
            return $track;
        }
        $ownPersonal = ($track['library_scope'] ?? '') === 'personal'
            && (int) ($track['owner_user_id'] ?? 0) === (int) $context['auth']['user_id'];
        if (!$ownPersonal) {
            throw new CampaignException('audio_track_not_found', 'Audio track is not available in this campaign.', 404);
        }
        return $track;
    }

    private function trackRow(int $trackId): array
    {
        $row = $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.scope AS library_scope, libraries.system_id AS library_system_id, libraries.setting_id AS library_setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->where('tracks.id', $trackId)->where('tracks.status', 'ready')->where('tracks.deleted_at', null)->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        return $row;
    }

    private function trackAvailable(array $campaign, int $campaignId, array $track): bool
    {
        $setting = ($track['library_scope'] ?? '') === 'system'
            && (empty($track['library_system_id']) || (int) $track['library_system_id'] === (int) ($campaign['rpg_system_id'] ?? 0))
            && (empty($track['library_setting_id']) || (int) $track['library_setting_id'] === (int) ($campaign['rpg_universe_id'] ?? 0));
        if ($setting) {
            return true;
        }
        return $this->campaignTracks->where('campaign_id', $campaignId)
            ->where('audio_track_id', (int) $track['id'])->where('is_enabled', 1)->countAllResults() > 0;
    }

    private function attachTrack(int $campaignId, int $trackId, int $userId): void
    {
        $link = $this->campaignTracks->where('campaign_id', $campaignId)->where('audio_track_id', $trackId)->first();
        if ($link) {
            if (empty($link['is_enabled'])) {
                $this->campaignTracks->update((int) $link['id'], ['is_enabled' => 1]);
            }
            return;
        }
        $this->campaignTracks->insert([
            'campaign_id' => $campaignId, 'audio_track_id' => $trackId,
            'added_by_user_id' => $userId, 'is_enabled' => 1, 'sort_order' => 0,
        ]);
    }

    private function playbackRow(int $campaignId, string $playbackId): array
    {
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $playbackId)) {
            throw new CampaignException('sound_effect_playback_not_found', 'Sound effect playback was not found.', 404);
        }
        $builder = $this->db->table('sound_effect_playbacks playbacks')
            ->select('playbacks.*, tracks.title, tracks.category, tracks.source_type, tracks.external_url, tracks.storage_key, tracks.mime_type')
            ->join('audio_tracks tracks', 'tracks.id = playbacks.audio_track_id', 'inner')
            ->where('playbacks.campaign_id', $campaignId)->where('playbacks.playback_id', $playbackId)
            ->where('playbacks.stopped_at_ms', null);
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('tracks.media_asset_id');
        }
        $row = $builder->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('sound_effect_playback_not_found', 'Sound effect playback was not found.', 404);
        }
        return $row;
    }

    private function recipients(int $campaignId, $scope, $ids): array
    {
        $normalizedScope = strtolower(trim((string) $scope));
        if (!in_array($normalizedScope, ['all', 'gm', 'selected'], true)) {
            throw new CampaignException('validation_failed', 'Sound effect audience is invalid.', 422);
        }
        if (!is_array($ids) || count($ids) > 100) {
            throw new CampaignException('validation_failed', 'Sound effect recipients are invalid.', 422);
        }
        $normalizedIds = array_values(array_unique(array_filter(array_map(static fn ($id): int => (int) $id, $ids), static fn (int $id): bool => $id > 0)));
        if ($normalizedScope !== 'selected') {
            return ['scope' => $normalizedScope, 'ids' => []];
        }
        if (!$normalizedIds) {
            throw new CampaignException('validation_failed', 'At least one sound effect recipient is required.', 422);
        }
        $valid = $this->db->table('campaign_members')->select('user_id')->where('campaign_id', $campaignId)
            ->where('is_active', 1)->whereIn('user_id', $normalizedIds)->get()->getResultArray();
        $campaign = $this->db->table('campaigns')->select('game_master_id')->where('id', $campaignId)->get()->getRowArray();
        $validIds = array_map(static fn (array $row): int => (int) $row['user_id'], $valid);
        if ($campaign && in_array((int) $campaign['game_master_id'], $normalizedIds, true)) {
            $validIds[] = (int) $campaign['game_master_id'];
        }
        $validIds = array_values(array_unique($validIds));
        sort($validIds);
        $expected = $normalizedIds;
        sort($expected);
        if ($validIds !== $expected) {
            throw new CampaignException('sound_effect_recipient_forbidden', 'A recipient is outside the campaign.', 403);
        }
        return ['scope' => 'selected', 'ids' => $validIds];
    }

    private function bumpRevision(int $campaignId, int $userId): void
    {
        $row = $this->settings->find($campaignId);
        $data = ['campaign_id' => $campaignId, 'revision' => max(0, (int) ($row['revision'] ?? 0)) + 1, 'updated_by_user_id' => $userId];
        if ($row) {
            $this->settings->update($campaignId, $data);
        } else {
            $this->settings->insert($data);
        }
    }

    private function revision(int $campaignId): int
    {
        return (int) (($this->settings->find($campaignId)['revision'] ?? 1));
    }

    private function requireGameMaster(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (!$this->isGameMaster($context)) {
            throw new CampaignException('forbidden', 'Only the campaign Game Master can manage sound effects.', 403);
        }
        return $context;
    }

    private function isGameMaster(array $context): bool
    {
        return !empty($context['isGameMaster']) || !empty($context['isAdmin']);
    }

    private function screenName($value): string
    {
        return $this->limitedText($value, 80, false);
    }

    private function screenLayout(array $screen, array $payload): array
    {
        return [
            'grid_columns' => $this->integer(
                $payload['columns'] ?? $screen['grid_columns'] ?? self::DEFAULT_GRID_COLUMNS,
                1,
                self::MAX_GRID_COLUMNS
            ),
            'grid_rows' => $this->integer(
                $payload['rows'] ?? $screen['grid_rows'] ?? self::DEFAULT_GRID_ROWS,
                1,
                self::MAX_GRID_ROWS
            ),
            'text_lines' => $this->integer(
                $payload['textLines'] ?? $screen['text_lines'] ?? self::DEFAULT_TEXT_LINES,
                1,
                self::MAX_TEXT_LINES
            ),
            'pad_style' => $this->padStyle($payload['padStyle'] ?? $screen['pad_style'] ?? 'square'),
        ];
    }

    private function screenCapacity(array $screen): int
    {
        $layout = $this->screenLayout($screen, []);
        return $layout['grid_columns'] * $layout['grid_rows'];
    }

    private function padStyle($value): string
    {
        $style = strtolower(trim((string) $value));
        if (!in_array($style, self::PAD_STYLES, true)) {
            throw new CampaignException('validation_failed', 'Sound effect screen style is invalid.', 422);
        }
        return $style;
    }

    private function integer($value, int $minimum, int $maximum): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => $minimum, 'max_range' => $maximum],
        ]);
        if ($integer === false) {
            throw new CampaignException('validation_failed', 'Sound effect screen numeric setting is invalid.', 422);
        }
        return (int) $integer;
    }

    private function icon($value): string
    {
        $icon = strtolower(trim((string) $value));
        return in_array($icon, ['waveform', 'swords', 'monster', 'explosion', 'weather', 'magic', 'bell', 'music', 'fire', 'door'], true) ? $icon : 'waveform';
    }

    private function color($value): string
    {
        $color = strtolower(trim((string) $value));
        if (!preg_match('/^#[0-9a-f]{6}$/', $color)) {
            throw new CampaignException('validation_failed', 'Sound effect slot color is invalid.', 422);
        }
        return $color;
    }

    private function shortcut($value): ?string
    {
        $shortcut = strtoupper(trim((string) $value));
        if ($shortcut === '') {
            return null;
        }
        if (strlen($shortcut) > 24 || !preg_match('/^[A-Z0-9+_-]+$/', $shortcut)) {
            throw new CampaignException('validation_failed', 'Sound effect shortcut is invalid.', 422);
        }
        return $shortcut;
    }

    private function limitedText($value, int $length, bool $required): string
    {
        $text = trim((string) $value);
        if (($required && $text === '') || mb_strlen($text) > $length || preg_match('/[\x00-\x1F\x7F]/u', $text)) {
            throw new CampaignException('validation_failed', 'Sound effect text is invalid.', 422);
        }
        return $text;
    }

    private function positiveId($value, string $code): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException($code, 'Resource was not found.', 404);
        }
        return (int) $id;
    }

    private function number($value, float $minimum, float $maximum): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)
            || (float) $value < $minimum || (float) $value > $maximum) {
            throw new CampaignException('validation_failed', 'Sound effect numeric setting is invalid.', 422);
        }
        return (float) $value;
    }

    private function jsonArray($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }
        return is_array($value) ? array_values(array_map('intval', $value)) : [];
    }

    private function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
