<?php

use App\Services\Region\RegionPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class RegionPayloadValidatorTest extends CIUnitTestCase
{
    private function square(): array
    {
        return [[
            ['x' => 0, 'y' => 0],
            ['x' => 100, 'y' => 0],
            ['x' => 100, 'y' => 100],
            ['x' => 0, 'y' => 100],
        ]];
    }

    public function testAcceptsMultiplePolygonDarknessConfiguration(): void
    {
        $result = (new RegionPayloadValidator())->create([
            'name' => 'Dark interior',
            'polygons' => $this->square(),
            'darknessMode' => 'add',
            'darknessValue' => 0.65,
            'disableGlobalIllumination' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('add', $result['data']['darkness_mode']);
        $this->assertSame(0.65, $result['data']['darkness_value']);
        $this->assertSame(1, $result['data']['disable_global_illumination']);
        $this->assertCount(1, json_decode($result['data']['polygons_json'], true));
    }

    public function testRejectsInvalidPolygonAndOutOfRangeDarkness(): void
    {
        $result = (new RegionPayloadValidator())->create([
            'polygons' => [[['x' => 0, 'y' => 0], ['x' => 10, 'y' => 0]]],
            'darknessValue' => 1.1,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('polygons', $result['errors']);
        $this->assertArrayHasKey('darknessValue', $result['errors']);
    }

    public function testUpdateRequiresOptimisticRevision(): void
    {
        $result = (new RegionPayloadValidator())->update(['name' => 'Changed']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
    }
}
