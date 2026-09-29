<?php

use App\Services\Wall\WallCollisionService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class WallCollisionServiceTest extends CIUnitTestCase
{
    private function wall(array $changes = []): array
    {
        return $changes + [
            'type' => 'wall',
            'x1' => 50,
            'y1' => 0,
            'x2' => 50,
            'y2' => 100,
            'blocks_movement' => 1,
            'enabled' => 1,
            'door_state' => null,
        ];
    }

    public function testClosedWallBlocksCrossingMovement(): void
    {
        $service = new WallCollisionService();
        $blocked = $service->blocksMovement(
            ['x' => 10, 'y' => 50],
            ['x' => 90, 'y' => 50],
            [$this->wall()]
        );

        $this->assertTrue($blocked);
    }

    public function testOpenDoorAllowsMovement(): void
    {
        $service = new WallCollisionService();
        $door = $this->wall(['type' => 'door', 'door_state' => 'open']);

        $this->assertFalse($service->blocksMovement(
            ['x' => 10, 'y' => 50],
            ['x' => 90, 'y' => 50],
            [$door]
        ));
    }

    public function testNonBlockingWallAllowsMovement(): void
    {
        $service = new WallCollisionService();
        $wall = $this->wall(['blocks_movement' => 0]);

        $this->assertFalse($service->blocksMovement(
            ['x' => 10, 'y' => 50],
            ['x' => 90, 'y' => 50],
            [$wall]
        ));
    }

    public function testDisabledWallAllowsMovement(): void
    {
        $this->assertFalse((new WallCollisionService())->blocksMovement(
            ['x' => 10, 'y' => 50],
            ['x' => 90, 'y' => 50],
            [$this->wall(['enabled' => 0])]
        ));
    }
}
