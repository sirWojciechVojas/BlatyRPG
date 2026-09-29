<?php

namespace App\Services\Wall;

use App\Models\SceneWallModel;
use CodeIgniter\Database\BaseConnection;

final class WallCollisionService
{
    private const EPSILON = 0.000001;
    private $walls;

    public function __construct(?BaseConnection $db = null, ?SceneWallModel $walls = null)
    {
        $connection = $db ?: \Config\Database::connect();
        $this->walls = $walls ?: new SceneWallModel($connection);
    }

    public function blocksSceneMovement(
        int $campaignId,
        int $sceneId,
        array $start,
        array $end
    ): bool {
        $walls = $this->walls->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('blocks_movement', 1)
            ->where('enabled', 1)->findAll();
        return $this->blocksMovement($start, $end, $walls);
    }

    public function blocksMovement(array $start, array $end, array $walls): bool
    {
        if ($this->samePoint($start, $end)) return false;
        foreach ($walls as $wall) {
            if (array_key_exists('enabled', $wall) && empty($wall['enabled'])) continue;
            if (empty($wall['blocks_movement'])) continue;
            $doorType = (string) ($wall['door_type'] ?? (
                ($wall['type'] ?? 'wall') !== 'wall' ? $wall['type'] : 'none'
            ));
            if ($doorType !== 'none' && ($wall['door_state'] ?? null) === 'open') {
                continue;
            }
            $a = ['x' => (float) $wall['x1'], 'y' => (float) $wall['y1']];
            $b = ['x' => (float) $wall['x2'], 'y' => (float) $wall['y2']];
            if ($this->onSegment($a, $b, $start) && !$this->onSegment($a, $b, $end)) {
                continue;
            }
            if ($this->intersects($start, $end, $a, $b)) return true;
        }
        return false;
    }

    private function intersects(array $a, array $b, array $c, array $d): bool
    {
        $o1 = $this->orientation($a, $b, $c);
        $o2 = $this->orientation($a, $b, $d);
        $o3 = $this->orientation($c, $d, $a);
        $o4 = $this->orientation($c, $d, $b);
        if ($o1 * $o2 < 0 && $o3 * $o4 < 0) return true;
        return ($o1 === 0 && $this->onSegment($a, $b, $c))
            || ($o2 === 0 && $this->onSegment($a, $b, $d))
            || ($o3 === 0 && $this->onSegment($c, $d, $a))
            || ($o4 === 0 && $this->onSegment($c, $d, $b));
    }

    private function orientation(array $a, array $b, array $c): int
    {
        $value = ($b['x'] - $a['x']) * ($c['y'] - $a['y'])
            - ($b['y'] - $a['y']) * ($c['x'] - $a['x']);
        if (abs($value) <= self::EPSILON) return 0;
        return $value > 0 ? 1 : -1;
    }

    private function onSegment(array $a, array $b, array $point): bool
    {
        if ($this->orientation($a, $b, $point) !== 0) return false;
        return $point['x'] >= min($a['x'], $b['x']) - self::EPSILON
            && $point['x'] <= max($a['x'], $b['x']) + self::EPSILON
            && $point['y'] >= min($a['y'], $b['y']) - self::EPSILON
            && $point['y'] <= max($a['y'], $b['y']) + self::EPSILON;
    }

    private function samePoint(array $a, array $b): bool
    {
        return abs($a['x'] - $b['x']) <= self::EPSILON
            && abs($a['y'] - $b['y']) <= self::EPSILON;
    }
}
