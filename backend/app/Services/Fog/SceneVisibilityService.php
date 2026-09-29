<?php

namespace App\Services\Fog;

use App\Models\SceneLightModel;
use App\Models\SceneRegionModel;
use App\Models\SceneWallModel;
use App\Services\Token\TokenAccessService;
use CodeIgniter\Database\BaseConnection;

/** Server-side center-point LOS gate used to avoid exposing out-of-view tokens. */
final class SceneVisibilityService
{
    private $walls;
    private $lights;
    private $access;
    private $regions;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneWallModel $walls = null,
        ?SceneLightModel $lights = null,
        ?TokenAccessService $access = null,
        ?SceneRegionModel $regions = null
    ) {
        $connection = $db ?: \Config\Database::connect();
        $this->walls = $walls ?: new SceneWallModel($connection);
        $this->lights = $lights ?: new SceneLightModel($connection);
        $this->access = $access ?: new TokenAccessService($connection);
        $this->regions = $regions ?: new SceneRegionModel($connection);
    }

    public function filter(array $auth, int $campaignId, array $scene, array $tokens): array
    {
        if (empty($scene['fog_enabled']) || empty($scene['dynamic_vision'])) return $tokens;
        $wallRows = $this->walls->where('campaign_id', $campaignId)
            ->where('scene_id', (int) $scene['id'])->findAll();
        $walls = array_values(array_filter(
            $wallRows,
            fn (array $wall): bool => $this->blocks($wall, 'sight')
                || ($wall['restriction_type'] ?? 'normal') === 'proximity'
        ));
        $lightWalls = array_values(array_filter(
            $wallRows,
            fn (array $wall): bool => $this->blocks($wall, 'light')
                || ($wall['restriction_type'] ?? 'normal') === 'proximity'
        ));
        $lights = $this->lights->where('campaign_id', $campaignId)
            ->where('scene_id', (int) $scene['id'])->where('enabled', 1)->where('hidden', 0)->findAll();
        $regions = $this->regions->where('campaign_id', $campaignId)
            ->where('scene_id', (int) $scene['id'])->where('enabled', 1)->findAll();
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
            $auth, $campaignId, $scene, $sources, $walls, $lightWalls, $lights,
            $regions
        ): bool {
            if ($this->access->canControl($auth, $campaignId, $target, false)) return true;
            $point = $this->center($target);
            foreach ($sources as $source) {
                $vision = (array) ($source['vision_json'] ?? []);
                $origin = $this->center($source);
                $distance = hypot($point['x'] - $origin['x'], $point['y'] - $origin['y']);
                $range = max(0.0, (float) ($vision['range'] ?? 600));
                if ($distance > $range || !$this->insideAngle($origin, $point, (float) ($source['facing'] ?? 0), (float) ($vision['angle'] ?? 360))) continue;
                if (!empty($vision['constrainedByWalls'])
                    && !$this->lineClear($origin, $point, $walls, $range, $scene, 'sight')) continue;
                if (empty($vision['limitByLight'])) return true;
                if ($distance <= (float) ($vision['minimumRadius'] ?? 0)) return true;
                $mode = (string) ($vision['mode'] ?? (!empty($vision['darkvision'])
                    ? 'darkvision' : 'basic'));
                if ($mode === 'tremorsense'
                    && abs((float) ($target['elevation'] ?? 0)) <= 0.01) return true;
                if (in_array($mode, [
                    'darkvision', 'light_amplification', 'monochromatic',
                ], true)) return true;
                if (!empty($vision['darkvision'])
                    && $distance <= (float) ($vision['darkvisionRange'] ?? 0)) return true;
                if ($this->illuminated($point, $scene, $lights, $lightWalls, $regions)) return true;
            }
            return false;
        }));
    }

    private function illuminated(
        array $point,
        array $scene,
        array $lights,
        array $walls,
        array $regions
    ): bool
    {
        $darkness = $this->effectiveDarkness($point, $scene, $regions);
        $globalDisabled = false;
        foreach ($regions as $region) {
            if (!empty($region['disable_global_illumination'])
                && $this->insideRegion($point, $region)) {
                $globalDisabled = true;
                break;
            }
        }
        $lit = !$globalDisabled
            && !empty($scene['global_illumination'])
            && $darkness <= (float) ($scene['global_illumination_threshold'] ?? 1);
        foreach ($lights as $light) {
            if ($darkness < (float) ($light['darkness_min'] ?? 0)
                || $darkness > (float) ($light['darkness_max'] ?? 1)) continue;
            $origin = ['x' => (float) $light['x'], 'y' => (float) $light['y']];
            $range = (float) ($light['dim_radius'] ?? 0);
            $distance = hypot($point['x'] - $origin['x'], $point['y'] - $origin['y']);
            if ($distance > $range) continue;
            if (!$this->insideAngle($origin, $point, (float) ($light['direction'] ?? 0), (float) ($light['angle'] ?? 360))) continue;
            if (!empty($light['constrained_by_walls'])
                && !$this->lineClear($origin, $point, $walls, $range, $scene, 'light')) continue;
            if (($light['source_type'] ?? '') === 'darkness') return false;
            $lit = true;
        }
        return $lit;
    }

    private function blocks(array $wall, string $kind): bool
    {
        if (empty($wall['enabled'])) return false;
        $doorType = (string) ($wall['door_type'] ?? (
            ($wall['type'] ?? 'wall') !== 'wall' ? $wall['type'] : 'none'
        ));
        if ($doorType !== 'none' && ($wall['door_state'] ?? null) === 'open') return false;
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

    private function lineClear(
        array $start,
        array $end,
        array $walls,
        float $maximumRange,
        array $scene,
        string $kind
    ): bool
    {
        foreach ($walls as $wall) {
            $a = ['x' => (float) $wall['x1'], 'y' => (float) $wall['y1']];
            $b = ['x' => (float) $wall['x2'], 'y' => (float) $wall['y2']];
            $position = $this->intersectionPosition($start, $end, $a, $b);
            if ($position === null) continue;
            $doorType = (string) ($wall['door_type'] ?? (
                ($wall['type'] ?? 'wall') !== 'wall' ? $wall['type'] : 'none'
            ));
            if ($doorType !== 'none' && ($wall['door_state'] ?? null) === 'open') {
                continue;
            }
            if (($wall['restriction_type'] ?? 'normal') === 'proximity') {
                $distance = hypot($end['x'] - $start['x'], $end['y'] - $start['y']);
                $toWindow = $distance * $position;
                $threshold = max(0.001, (float) ($wall['proximity_threshold'] ?? 10));
                $pixelsPerUnit = (float) ($scene['grid_size'] ?? 100)
                    / max(0.001, (float) ($scene['grid_distance'] ?? 5));
                $limit = $threshold * $pixelsPerUnit;
                $penetration = max(0.0, $maximumRange - $toWindow)
                    / (1 + $toWindow / $limit);
                if ($distance - $toWindow > $penetration) return false;
                continue;
            }
            if ($this->blocks($wall, $kind)) {
                return false;
            }
        }
        return true;
    }

    private function effectiveDarkness(array $point, array $scene, array $regions): float
    {
        $value = $this->transitionedDarkness($scene);
        foreach ($regions as $region) {
            if (!$this->insideRegion($point, $region)) continue;
            $amount = max(0.0, min(1.0, (float) ($region['darkness_value'] ?? 0)));
            $mode = (string) ($region['darkness_mode'] ?? 'override');
            if ($mode === 'add') $value += $amount;
            elseif ($mode === 'subtract') $value -= $amount;
            else $value = $amount;
            $value = max(0.0, min(1.0, $value));
        }
        return $value;
    }

    private function transitionedDarkness(array $scene): float
    {
        $fallback = max(0.0, min(1.0, (float) ($scene['darkness_level'] ?? 0)));
        $started = $scene['darkness_transition_started_at'] ?? null;
        $duration = (int) ($scene['darkness_transition_duration'] ?? 0);
        if (!$started || $duration < 1) return $fallback;
        try {
            $start = new \DateTimeImmutable((string) $started, new \DateTimeZone('UTC'));
            $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            return $fallback;
        }
        $elapsed = max(
            0.0,
            ((float) $now->format('U.u') - (float) $start->format('U.u')) * 1000
        );
        $progress = min(1.0, $elapsed / $duration);
        $from = (float) ($scene['darkness_transition_from'] ?? $fallback);
        $to = (float) ($scene['darkness_transition_to'] ?? $fallback);
        return max(0.0, min(1.0, $from + ($to - $from) * $progress));
    }

    private function insideRegion(array $point, array $region): bool
    {
        $polygons = $region['polygons_json'] ?? [];
        if (is_string($polygons)) $polygons = json_decode($polygons, true);
        if (!is_array($polygons)) return false;
        foreach ($polygons as $polygon) {
            if (!is_array($polygon) || count($polygon) < 3) continue;
            $inside = false;
            $previous = count($polygon) - 1;
            foreach ($polygon as $index => $current) {
                $before = $polygon[$previous];
                $crosses = ((float) $current['y'] > $point['y'])
                    !== ((float) $before['y'] > $point['y']);
                if ($crosses && $point['x'] < (
                    ((float) $before['x'] - (float) $current['x'])
                    * ($point['y'] - (float) $current['y'])
                    / ((float) $before['y'] - (float) $current['y'] ?: PHP_FLOAT_EPSILON)
                    + (float) $current['x']
                )) $inside = !$inside;
                $previous = $index;
            }
            if ($inside) return true;
        }
        return false;
    }

    private function intersectionPosition(
        array $a,
        array $b,
        array $c,
        array $d
    ): ?float {
        $cross = static fn (array $p, array $q): float => $p['x'] * $q['y'] - $p['y'] * $q['x'];
        $r = ['x' => $b['x'] - $a['x'], 'y' => $b['y'] - $a['y']];
        $s = ['x' => $d['x'] - $c['x'], 'y' => $d['y'] - $c['y']];
        $denominator = $cross($r, $s);
        if (abs($denominator) < 0.000001) return null;
        $delta = ['x' => $c['x'] - $a['x'], 'y' => $c['y'] - $a['y']];
        $t = $cross($delta, $s) / $denominator;
        $u = $cross($delta, $r) / $denominator;
        return $t > 0.00001 && $t < 0.99999 && $u >= 0 && $u <= 1 ? $t : null;
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
