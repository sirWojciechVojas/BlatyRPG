<?php

namespace App\Services\Audio;

use App\Models\CampaignJukeboxSettingModel;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

final class JukeboxStateService
{
    public const CHANNELS = [
        'music' => 'music',
        'ambient-1' => 'ambient',
        'ambient-2' => 'ambient',
        'sfx' => 'sfx',
        'external-1' => 'external',
        'external-2' => 'external',
    ];

    private $db;
    private $guard;
    private $settings;

    public function __construct(?BaseConnection $db = null, ?CampaignGuardService $guard = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->settings = new CampaignJukeboxSettingModel($this->db);
    }

    public function state(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $row = $this->settings->find($campaignId);
        return [
            'state' => $row ? $this->normalizeState((array) $row['state_json'], $campaignId, false) : $this->defaultState(),
            'settings' => $row && is_array($row['settings_json']) ? $row['settings_json'] : $this->defaultSettings(),
            'revision' => $row ? (int) $row['revision'] : 0,
            'updatedAt' => $row['updated_at'] ?? null,
            'capabilities' => ['canControl' => $this->isGameMaster($context)],
        ];
    }

    public function save(array $auth, int $campaignId, array $payload): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (!$this->isGameMaster($context)) {
            throw new CampaignException('forbidden', 'Only the campaign Game Master can control the jukebox.', 403);
        }
        if (array_diff(array_keys($payload), ['state', 'settings'])) {
            throw new CampaignException('validation_failed', 'Jukebox payload is invalid.', 422);
        }
        $state = $this->normalizeState((array) ($payload['state'] ?? []), $campaignId, true);
        $settings = $this->normalizeSettings((array) ($payload['settings'] ?? []));
        $existing = $this->settings->find($campaignId);
        $revision = max(0, (int) ($existing['revision'] ?? 0)) + 1;
        $data = [
            'campaign_id' => $campaignId,
            'state_json' => $state,
            'settings_json' => $settings,
            'revision' => $revision,
            'updated_by_user_id' => (int) $context['auth']['user_id'],
        ];
        $ok = $existing
            ? $this->settings->update($campaignId, $data)
            : $this->settings->insert($data);
        if (!$ok) {
            throw new CampaignException('jukebox_write_failed', 'Jukebox state could not be saved.', 500);
        }
        return [
            'state' => $state,
            'settings' => $settings,
            'revision' => $revision,
            'updatedAt' => date(DATE_ATOM),
            'capabilities' => ['canControl' => true],
        ];
    }

    public function defaultState(): array
    {
        $state = [];
        foreach (self::CHANNELS as $channelId => $category) {
            $state[$channelId] = [
                'channelId' => $channelId,
                'category' => $category,
                'trackId' => null,
                'status' => 'stopped',
                'position' => 0.0,
                'duration' => null,
                'startedAt' => null,
                'executeAt' => null,
                'loop' => false,
                'volume' => 1.0,
                'muted' => false,
                'sourceType' => $category === 'external' ? 'external-input' : null,
                'playlistId' => null,
                'sourceLabel' => '',
                'deviceLabel' => '',
                'deviceSlot' => null,
                'fadeMs' => 0,
            ];
        }
        return $state;
    }

    private function normalizeState(array $value, int $campaignId, bool $verifyTracks): array
    {
        $result = $this->defaultState();
        foreach (self::CHANNELS as $channelId => $category) {
            $source = isset($value[$channelId]) && is_array($value[$channelId]) ? $value[$channelId] : [];
            $trackId = $this->nullablePositiveId($source['trackId'] ?? null);
            if ($verifyTracks && $trackId !== null && !$this->trackAvailable($campaignId, $trackId)) {
                throw new CampaignException('audio_track_not_found', 'Jukebox audio track is not available.', 404);
            }
            $status = strtolower((string) ($source['status'] ?? 'stopped'));
            if (!in_array($status, ['loading', 'playing', 'paused', 'stopped'], true)) {
                $status = 'stopped';
            }
            $result[$channelId] = [
                'channelId' => $channelId,
                'category' => $category,
                'trackId' => $trackId,
                'status' => $status,
                'position' => $this->number($source['position'] ?? 0, 0, 86400),
                'duration' => isset($source['duration']) ? $this->number($source['duration'], 0, 86400) : null,
                'startedAt' => $this->nullableTimestamp($source['startedAt'] ?? null),
                'executeAt' => $this->nullableTimestamp($source['executeAt'] ?? null),
                'loop' => !empty($source['loop']),
                'volume' => $this->number($source['volume'] ?? 1, 0, 1),
                'muted' => !empty($source['muted']),
                'sourceType' => $this->safeSourceType($source['sourceType'] ?? ($category === 'external' ? 'external-input' : null)),
                'playlistId' => $this->nullablePositiveId($source['playlistId'] ?? null),
                'sourceLabel' => $this->safeLabel($source['sourceLabel'] ?? ''),
                'deviceLabel' => $this->safeLabel($source['deviceLabel'] ?? ''),
                'deviceSlot' => $this->safeDeviceSlot($source['deviceSlot'] ?? null),
                'fadeMs' => (int) $this->number($source['fadeMs'] ?? 0, 0, 60000),
            ];
        }
        return $result;
    }

    private function normalizeSettings(array $settings): array
    {
        return [
            'duckingEnabled' => !array_key_exists('duckingEnabled', $settings) || !empty($settings['duckingEnabled']),
            'musicDuckDb' => $this->number($settings['musicDuckDb'] ?? -6, -24, 0),
            'ambientDuckDb' => $this->number($settings['ambientDuckDb'] ?? -4, -24, 0),
        ];
    }

    private function defaultSettings(): array
    {
        return ['duckingEnabled' => true, 'musicDuckDb' => -6.0, 'ambientDuckDb' => -4.0];
    }

    private function trackAvailable(int $campaignId, int $trackId): bool
    {
        $linked = $this->db->table('campaign_audio_tracks campaign_tracks')
            ->join('audio_tracks tracks', 'tracks.id = campaign_tracks.audio_track_id', 'inner')
            ->where('campaign_tracks.campaign_id', $campaignId)
            ->where('campaign_tracks.audio_track_id', $trackId)
            ->where('campaign_tracks.is_enabled', 1)
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null)
            ->countAllResults() > 0;
        if ($linked) {
            return true;
        }
        $campaign = $this->db->table('campaigns')->where('id', $campaignId)->get()->getRowArray();
        $row = $this->db->table('audio_tracks tracks')
            ->select('libraries.scope, libraries.system_id, libraries.setting_id')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'inner')
            ->where('tracks.id', $trackId)->where('tracks.status', 'ready')
            ->where('tracks.deleted_at', null)->where('libraries.is_active', 1)->get()->getRowArray();
        return $campaign && $row && $row['scope'] === 'system'
            && (empty($row['system_id']) || (int) $row['system_id'] === (int) ($campaign['rpg_system_id'] ?? 0))
            && (empty($row['setting_id']) || (int) $row['setting_id'] === (int) ($campaign['rpg_universe_id'] ?? 0));
    }

    private function nullablePositiveId($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException('validation_failed', 'Jukebox track id is invalid.', 422);
        }
        return (int) $id;
    }

    private function number($value, float $minimum, float $maximum): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return $minimum;
        }
        return max($minimum, min($maximum, (float) $value));
    }

    private function nullableTimestamp($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $this->number($value, 0, 9999999999999);
    }

    private function safeSourceType($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $source = strtolower(trim((string) $value));
        return in_array($source, ['system', 'upload', 'external', 'external-input'], true) ? $source : null;
    }

    private function safeLabel($value): string
    {
        $label = trim((string) $value);
        return mb_substr(preg_replace('/[\x00-\x1F\x7F]/u', '', $label) ?: '', 0, 180);
    }

    private function safeDeviceSlot($value): ?string
    {
        $slot = trim((string) $value);
        return in_array($slot, ['external-1', 'external-2'], true) ? $slot : null;
    }

    private function isGameMaster(array $context): bool
    {
        return !empty($context['isGameMaster']) || !empty($context['isAdmin']);
    }
}
