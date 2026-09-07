<?php

namespace App\Services\Fog;

use App\Models\SceneLightModel;
use App\Models\SceneWallModel;
use App\Services\Token\TokenAccessService;
use CodeIgniter\Database\BaseConnection;

/** Server-side center-point LOS gate used to avoid exposing out-of-view tokens. */
final class SceneVisibilityService
{
    private $walls;
    private $lights;
    private $access;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneWallModel $walls = null,
        ?SceneLightModel $lights = null,
        ?TokenAccessService $access = null
    ) {
        $connection = $db ?: \Config\Database::connect();
        $this->walls = $walls ?: new SceneWallModel($connection);
        $this->lights = $lights ?: new SceneLightModel($connection);
        $this->access = $access ?: new TokenAccessService($connection);
    }

    public function filter(array $auth, int $campaignId, array $scene, array $tokens): array
    {
        if (empty($scene['fog_enabled']) || empty($scene['dynamic_vision'])) return $tokens;
        $wallRows = $this->walls->where('campaign_id', $campaignId)
            ->where('scene_id', (int) $scene['id'])->findAll();
        $walls = array_values(array_filter(
            $wallRows,
            fn (array $wall): bool => $this->blocks($wall, 'sight')
        ));
        $lightWalls = array_values(array_filter(
            $wallRows,
            fn (array $wall): bool => $this->blocks($wall, 'light')
        ));
        $lights = $this->lights->where('campaign_id', $campaignId)
            ->where('scene_id', (int) $scene['id'])->where('enabled', 1)->where('hidden', 0)->findAll();
        $sources = array_values(array_filter($tokens, function (array $token) use ($auth, $campaignId): bool {
            $vision = (array) ($token['vision_json'] ?? []);
            return !empty($vision['enabled']) && $this->access->canControl($auth, $campaignId, $token, false);
        }));
        if (!$sources) {
            return array_values(array_filter(
                $tokens,
                fn (array $token): bool => $this->access->canControl($auth, $campaignId, $token, false)
            ));
        }
        return array_values(array_filter($tokens, function (array $target) use (
            $auth, $campaignId, $scene, $sources, $walls, $lightWalls, $lights
        ): bool {
            if ($this->access->canControl($auth, $campaignId, $target, false)) return true;
            $point = $this->center($target);
            foreach ($sources as $source) {
                $vision = (array) ($source['vision_json'] ?? []);
                $origin = $this->center($source);
                $distance = hypot($point['x'] - $origin['x'], $point['y'] - $origin['y']);
                $range = max(0.0, (float) ($vision['range'] ?? 600));
                if ($distance > $range || !$this->insideAngle($origin, $point, (float) ($source['facing'] ?? 0), (float) ($vision['angle'] ?? 360))) continue;
                if (!empty($vision['constrainedByWalls']) && !$this->lineClear($origin, $point, $walls)) continue;
                if (empty($vision['limitByLight'])) return true;
                if ($distance <= (float) ($vision['minimumRadius'] ?? 0)) return true;
                if (!empty($vision['darkvision']) && $distance <= (float) ($vision['darkvisionRange'] ?? 0)) return true;
                if ($this->illuminated($point, $scene, $lights, $lightWalls)) return true;
            }
            return false;
        }));
    }

    private function illuminated(array $point, array $scene, array $lights, array $walls): bool
    {
        $lit = (float) ($scene['global_light_level'] ?? 0) > 0.02;
        foreach ($lights as $light) {
            $origin = ['x' => (float) $light['x'], 'y' => (float) $light['y']];
            $distance = hypot($point['x'] - $origin['x'], $point['y'] - $origin['y']);
            if ($distance > (float) ($light['dim_radius'] ?? 0)) continue;
            if (!$this->insideAngle($origin, $point, (float) ($light['direction'] ?? 0), (float) ($light['angle'] ?? 360))) continue;
            if (!empty($light['constrained_by_walls']) && !$this->lineClear($origin, $point, $walls)) continue;
            if (($light['source_type'] ?? '') === 'darkness') return false;
            $lit = true;
        }
        return $lit;
    }

    private function blocks(array $wall, string $kind): bool
    {
        if (empty($wall['enabled'])) return false;
        if (($wall['type'] ?? 'wall') !== 'wall' && ($wall['door_state'] ?? null) === 'open') return false;
        return !empty($wall[$kind === 'sight' ? 'blocks_sight' : 'blocks_light']);
    }

    private function center(array $token): array
    {
        return [
            'x' => (float) $token['x'] + (float) ($token['width'] ?? 0) / 2,
            'y' => (float) $token['y'] + (float) ($token['height'] ?? 0) / 2,
        ];
    }

    private function insideAngle(array $origin, array $point, float $direction, float $angle): bool
    {
        if ($angle >= 359.999) return true;
        $bearing = rad2deg(atan2($point['y'] - $origin['y'], $point['x'] - $origin['x']));
        $delta = fmod($bearing - $direction + 540.0, 360.0) - 180.0;
        return abs($delta) <= max(1.0, $angle) / 2;
    }

    private function lineClear(array $start, array $end, array $walls): bool
    {
        foreach ($walls as $wall) {
            $a = ['x' => (float) $wall['x1'], 'y' => (float) $wall['y1']];
            $b = ['x' => (float) $wall['x2'], 'y' => (float) $wall['y2']];
            if ($this->intersects($start, $end, $a, $b)) return false;
        }
        return true;
    }

    private function intersects(array $a, array $b, array $c, array $d): bool
    {
        $cross = static fn (array $p, array $q): float => $p['x'] * $q['y'] - $p['y'] * $q['x'];
        $r = ['x' => $b['x'] - $a['x'], 'y' => $b['y'] - $a['y']];
        $s = ['x' => $d['x'] - $c['x'], 'y' => $d['y'] - $c['y']];
        $denominator = $cross($r, $s);
        if (abs($denominator) < 0.000001) return false;
        $delta = ['x' => $c['x'] - $a['x'], 'y' => $c['y'] - $a['y']];
        $t = $cross($delta, $s) / $denominator;
        $u = $cross($delta, $r) / $denominator;
        return $t > 0.00001 && $t < 0.99999 && $u >= 0 && $u <= 1;
    }
}
