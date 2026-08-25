<?php

namespace App\Services\Token;

use App\Services\Wall\WallCollisionService;

final class TokenMovementService
{
    private $collisions;
    private $grid;

    public function __construct(
        ?WallCollisionService $collisions = null,
        ?TokenGridPositionService $grid = null
    ) {
        $this->collisions = $collisions ?: new WallCollisionService();
        $this->grid = $grid ?: new TokenGridPositionService();
    }

    public function apply(
        int $campaignId,
        int $sceneId,
        array $scene,
        array $token,
        array $changes,
        array $rawWaypoints,
        bool $canManage
    ): array {
        if (!array_key_exists('x', $changes) && !array_key_exists('y', $changes)) return [];
        $width = (float) ($changes['width'] ?? $token['width']);
        $height = (float) ($changes['height'] ?? $token['height']);
        $positions = [[
            'x' => (float) $token['x'],
            'y' => (float) $token['y'],
        ]];
        foreach (TokenMovementRoute::validate($rawWaypoints) as $waypoint) {
            $positions[] = $this->grid->snap($scene, $waypoint, $width, $height);
        }
        $positions[] = [
            'x' => (float) ($changes['x'] ?? $token['x']),
            'y' => (float) ($changes['y'] ?? $token['y']),
        ];
        $centers = $this->centers($positions, $width, $height);
        $this->assertSegmentsAllowed($campaignId, $sceneId, $centers);
        $cost = TokenMovementCost::route($scene, $centers);
        $range = max(0.0, (float) ($changes['movement_range'] ?? $token['movement_range'] ?? 6));
        $spent = max(0.0, (float) ($changes['movement_spent'] ?? $token['movement_spent'] ?? 0));
        if (!$canManage && $spent + $cost > $range + 0.0005) {
            throw new TokenException('movement_limit_exceeded', 'Token movement exceeds its available points.', 422, [
                'cost' => $cost,
                'spent' => $spent,
                'range' => $range,
                'remaining' => max(0.0, round($range - $spent, 3)),
            ]);
        }
        return ['movement_spent' => round($spent + $cost, 3)];
    }

    private function centers(array $positions, float $width, float $height): array
    {
        $result = [];
        foreach ($positions as $position) {
            $center = [
                'x' => (float) $position['x'] + $width / 2,
                'y' => (float) $position['y'] + $height / 2,
            ];
            $previous = end($result);
            if (!$previous || $previous['x'] !== $center['x'] || $previous['y'] !== $center['y']) {
                $result[] = $center;
            }
        }
        return $result;
    }

    private function assertSegmentsAllowed(int $campaignId, int $sceneId, array $points): void
    {
        for ($index = 1; $index < count($points); $index++) {
            if ($this->collisions->blocksSceneMovement(
                $campaignId, $sceneId, $points[$index - 1], $points[$index]
            )) {
                throw new TokenException('movement_blocked',
                    'Token movement is blocked by a wall or closed door.', 422);
            }
        }
    }
}
