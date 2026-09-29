<?php

namespace App\Services\Wall;

use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use App\Services\Token\TokenAccessService;
use CodeIgniter\Database\BaseConnection;

final class WallAudioProjectionService
{
    private $db;
    private $scenes;
    private $sceneAccess;
    private $tokenAccess;
    private $tracks = [];

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $sceneAccess = null,
        ?TokenAccessService $tokenAccess = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->sceneAccess = $sceneAccess ?: new SceneResourceAccessService();
        $this->tokenAccess = $tokenAccess ?: new TokenAccessService($this->db);
    }

    public function state(
        int $campaignId,
        int $sceneId,
        array $auth,
        ?int $selectedTokenId = null
    ): array {
        [$scene, $canManage] = $this->context($campaignId, $sceneId, $auth);
        $listeners = $this->listeners(
            $campaignId,
            $sceneId,
            $auth,
            $canManage,
            $selectedTokenId
        );
        $items = [];
        if ($listeners) {
            $walls = $this->db->table('scene_walls')
                ->where('campaign_id', $campaignId)->where('scene_id', $sceneId)
                ->where('enabled', 1)->where('deleted_at', null)
                ->groupStart()->where('door_type', 'secret')->orWhere('type', 'secret')
                ->groupEnd()->get()->getResultArray();
            foreach ($walls as $wall) {
                foreach ($this->rules($wall) as $rule) {
                    if (empty($rule['enabled']) || ($rule['trigger'] ?? '') !== 'proximityLoop') {
                        continue;
                    }
                    $projection = $this->project(
                        $campaignId,
                        $sceneId,
                        $scene,
                        $wall,
                        $rule,
                        $listeners,
                        true
                    );
                    if ($projection) $items[] = $projection;
                }
            }
        }
        return [
            'sceneId' => $sceneId,
            'activeLoops' => $items,
            'serverTime' => $this->milliseconds(),
        ];
    }

    public function cue(
        int $campaignId,
        int $sceneId,
        int $wallId,
        string $cue,
        array $auth,
        ?int $selectedTokenId = null
    ): array {
        if (!in_array($cue, ['open', 'close', 'lock', 'lockedAttempt'], true)) {
            throw new WallException('validation_failed', 'Wall sound cue is invalid.', 422);
        }
        [$scene, $canManage] = $this->context($campaignId, $sceneId, $auth);
        $listeners = $this->listeners(
            $campaignId,
            $sceneId,
            $auth,
            $canManage,
            $selectedTokenId
        );
        $wall = $this->db->table('scene_walls')->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $wallId)
            ->where('deleted_at', null)->get()->getRowArray();
        if (!$wall) throw new WallException('wall_not_found', 'Wall was not found.', 404);
        $items = [];
        foreach ($this->rules($wall) as $rule) {
            if (empty($rule['enabled']) || ($rule['trigger'] ?? '') !== $cue) continue;
            $projection = $this->project(
                $campaignId,
                $sceneId,
                $scene,
                $wall,
                $rule,
                $listeners,
                false
            );
            if ($projection) $items[] = $projection;
        }
        return [
            'sceneId' => $sceneId,
            'items' => $items,
            'serverTime' => $this->milliseconds(),
        ];
    }

    private function context(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new WallException(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details()
            );
        }
        $canManage = $this->sceneAccess->canManage(
            $auth,
            $campaignId,
            $sceneId,
            $result['capabilities']
        );
        return [$result['scene'], $canManage];
    }

    private function listeners(
        int $campaignId,
        int $sceneId,
        array $auth,
        bool $canManage,
        ?int $selectedTokenId
    ): array {
        $builder = $this->db->table('scene_tokens')->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('deleted_at', null);
        if ($canManage) {
            if (!$selectedTokenId) return [];
            $token = $builder->where('id', $selectedTokenId)->get()->getRowArray();
            return $token ? [$this->tokenPoint($token)] : [];
        }
        $tokens = $builder->get()->getResultArray();
        return array_values(array_map(
            fn (array $token): array => $this->tokenPoint($token),
            array_filter($tokens, fn (array $token): bool =>
                $this->tokenAccess->canControl($auth, $campaignId, $token, false))
        ));
    }

    private function tokenPoint(array $token): array
    {
        return [
            'x' => (float) $token['x'] + max(0.0, (float) ($token['width'] ?? 0)) / 2,
            'y' => (float) $token['y'] + max(0.0, (float) ($token['height'] ?? 0)) / 2,
        ];
    }

    private function rules(array $wall): array
    {
        $config = $wall['sound_config_json'] ?? [];
        if (is_string($config)) $config = json_decode($config, true);
        if (!is_array($config) || ($config['version'] ?? null) !== 2
            || !is_array($config['rules'] ?? null)) return [];
        return $config['rules'];
    }

    private function project(
        int $campaignId,
        int $sceneId,
        array $scene,
        array $wall,
        array $rule,
        array $listeners,
        bool $loop
    ): ?array {
        if (!$listeners) return null;
        $gain = 0.0;
        foreach ($listeners as $listener) {
            $gain = max($gain, $this->gain($wall, $rule, $listener, $scene));
        }
        if ($gain <= 0.0001) return null;
        $track = $this->track($campaignId, (int) ($rule['trackId'] ?? 0));
        if (!$track) return null;
        $ruleId = (string) ($rule['id'] ?? 'rule');
        $seed = $campaignId . ':' . $sceneId . ':' . $wall['id'] . ':'
            . $ruleId . ':' . (int) ($rule['trackId'] ?? 0);
        return [
            'playbackId' => 'wall-audio-' . hash('sha256', $seed . ($loop ? '' : ':' . bin2hex(random_bytes(8)))),
            'audio' => $track,
            'volume' => round($gain, 4),
            'loop' => $loop,
            'fadeInMs' => (int) ($rule['fadeInMs'] ?? 0),
            'fadeOutMs' => (int) ($rule['fadeOutMs'] ?? 0),
        ];
    }

    private function track(int $campaignId, int $trackId): ?array
    {
        if ($trackId < 1) return null;
        $key = $campaignId . ':' . $trackId;
        if (array_key_exists($key, $this->tracks)) return $this->tracks[$key];
        $builder = $this->db->table('audio_tracks tracks')
            ->select('tracks.id,tracks.title,tracks.source_type,tracks.duration_seconds,tracks.storage_key')
            ->join(
                'campaign_audio_tracks campaign_tracks',
                'campaign_tracks.audio_track_id = tracks.id',
                'inner'
            )->where('campaign_tracks.campaign_id', $campaignId)
            ->where('campaign_tracks.is_enabled', 1)->where('tracks.id', $trackId)
            ->where('tracks.status', 'ready')->where('tracks.deleted_at', null);
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('tracks.media_asset_id')->groupStart()
                ->where('tracks.storage_key !=', '')
                ->orWhere('tracks.media_asset_id IS NOT NULL', null, false)->groupEnd();
        } else {
            $builder->where('tracks.storage_key !=', '');
        }
        $row = $builder->get()->getRowArray();
        $this->tracks[$key] = $row ? [
            'id' => (int) $row['id'],
            'title' => (string) $row['title'],
            'sourceType' => (string) $row['source_type'],
            'url' => '/api/campaigns/' . $campaignId . '/audio/tracks/' . $trackId . '/file',
            'duration' => $row['duration_seconds'] === null
                ? null : (float) $row['duration_seconds'],
        ] : null;
        return $this->tracks[$key];
    }

    private function gain(array $wall, array $rule, array $listener, array $scene): float
    {
        $range = max(0.1, (float) ($rule['range'] ?? 10));
        $count = min(12, max(1, (int) ($rule['zoneCount'] ?? 3)));
        $distance = $this->distance($wall, $rule, $listener, $scene);
        if ($distance >= $range) return 0.0;
        $width = $range / $count;
        $index = min($count - 1, max(0, (int) floor($distance / $width)));
        $progress = ($distance - $width * $index) / max(0.0001, $width);
        $smooth = $progress * $progress * (3 - 2 * $progress);
        $volume = min(1.0, max(0.0, (float) ($rule['volume'] ?? 1)));
        $inner = $volume * (1 - $index / $count);
        $outer = $volume * (1 - ($index + 1) / $count);
        return max(0.0, $inner + ($outer - $inner) * $smooth);
    }

    private function distance(array $wall, array $rule, array $point, array $scene): float
    {
        $gridSize = max(0.0001, (float) ($scene['gridSize'] ?? $scene['grid_size'] ?? 100));
        $gridDistance = max(0.0001, (float) ($scene['gridDistance'] ?? $scene['grid_distance'] ?? 5));
        $unitsPerPixel = $gridDistance / $gridSize;
        $geometry = is_array($rule['geometry'] ?? null) ? $rule['geometry'] : [];
        if (($geometry['mode'] ?? 'points') === 'offsetLine') {
            $dx = (float) $wall['x2'] - (float) $wall['x1'];
            $dy = (float) $wall['y2'] - (float) $wall['y1'];
            $length = max(0.0001, hypot($dx, $dy));
            $offset = (float) ($geometry['offset'] ?? 0) / $unitsPerPixel;
            $nx = -$dy / $length;
            $ny = $dx / $length;
            $pixels = $this->segmentDistance($point, [
                'x1' => (float) $wall['x1'] + $nx * $offset,
                'y1' => (float) $wall['y1'] + $ny * $offset,
                'x2' => (float) $wall['x2'] + $nx * $offset,
                'y2' => (float) $wall['y2'] + $ny * $offset,
            ]);
            return $pixels * $unitsPerPixel;
        }
        $points = is_array($geometry['points'] ?? null) && $geometry['points']
            ? $geometry['points'] : [0.5];
        $minimum = INF;
        foreach ($points as $position) {
            $t = min(1.0, max(0.0, (float) $position));
            $x = (float) $wall['x1'] + ((float) $wall['x2'] - (float) $wall['x1']) * $t;
            $y = (float) $wall['y1'] + ((float) $wall['y2'] - (float) $wall['y1']) * $t;
            $minimum = min($minimum, hypot($point['x'] - $x, $point['y'] - $y));
        }
        return $minimum * $unitsPerPixel;
    }

    private function segmentDistance(array $point, array $segment): float
    {
        $dx = $segment['x2'] - $segment['x1'];
        $dy = $segment['y2'] - $segment['y1'];
        $squared = $dx * $dx + $dy * $dy;
        if ($squared <= 0.000001) {
            return hypot($point['x'] - $segment['x1'], $point['y'] - $segment['y1']);
        }
        $t = (($point['x'] - $segment['x1']) * $dx + ($point['y'] - $segment['y1']) * $dy)
            / $squared;
        $t = min(1.0, max(0.0, $t));
        return hypot(
            $point['x'] - ($segment['x1'] + $dx * $t),
            $point['y'] - ($segment['y1'] + $dy * $t)
        );
    }

    private function milliseconds(): int
    {
        return (int) floor(microtime(true) * 1000);
    }
}
