<?php

use App\Services\Token\TokenGridPositionService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenGridPositionServiceTest extends CIUnitTestCase
{
    private $grid;

    protected function setUp(): void
    {
        parent::setUp();
        $this->grid = new TokenGridPositionService();
    }

    public function testSnapsDifferentTokenSizesByTheirCenter(): void
    {
        $scene = [
            'grid_type' => 'square', 'grid_size' => 100,
            'grid_offset_x' => 10, 'grid_offset_y' => 20,
        ];

        $this->assertSame(
            ['x' => 110.0, 'y' => 120.0],
            $this->grid->snap($scene, ['x' => 72, 'y' => 83], 100, 100)
        );
        $this->assertSame(
            ['x' => 60.0, 'y' => 120.0],
            $this->grid->snap($scene, ['x' => 72, 'y' => 83], 200, 100)
        );
    }

    public function testSnapsBothHexOrientations(): void
    {
        $pointy = $this->grid->snap(
            ['grid_type' => 'hex_pointy', 'grid_size' => 100],
            ['x' => 97, 'y' => 40],
            100,
            100
        );
        $flat = $this->grid->snap(
            ['grid_type' => 'hex_flat', 'grid_size' => 100],
            ['x' => 40, 'y' => 97],
            100,
            100
        );

        $this->assertSame(['x' => 100.0, 'y' => 36.603], $pointy);
        $this->assertSame(['x' => 36.603, 'y' => 100.0], $flat);
    }

    public function testPreservesGridlessCoordinates(): void
    {
        $this->assertSame(
            ['x' => 17.25, 'y' => 44.75],
            $this->grid->snap(
                ['grid_type' => 'gridless'],
                ['x' => 17.25, 'y' => 44.75],
                160,
                80
            )
        );
    }
}
