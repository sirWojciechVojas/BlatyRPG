<?php

use App\Services\Token\TokenMovementCost;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenMovementCostTest extends CIUnitTestCase
{
    public function testCountsSquareDiagonalMovementInCells(): void
    {
        $cost = TokenMovementCost::route(
            ['grid_type' => 'square', 'grid_size' => 100],
            [['x' => 50, 'y' => 50], ['x' => 250, 'y' => 150]]
        );

        $this->assertSame(2.0, $cost);
    }

    public function testAddsHexagonalRouteSegments(): void
    {
        $cost = TokenMovementCost::route(
            ['grid_type' => 'hex_flat', 'grid_size' => 100],
            [['x' => 0, 'y' => 0], ['x' => 87, 'y' => 50], ['x' => 173, 'y' => 100]]
        );

        $this->assertSame(2.0, $cost);
    }

    public function testGridlessMovementUsesGridSizeAsOnePoint(): void
    {
        $cost = TokenMovementCost::route(
            ['grid_type' => 'gridless', 'grid_size' => 50],
            [['x' => 0, 'y' => 0], ['x' => 30, 'y' => 40]]
        );

        $this->assertSame(1.0, $cost);
    }
}
