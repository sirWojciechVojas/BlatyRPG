<?php

use App\Services\Light\LightPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class LightPayloadValidatorTest extends CIUnitTestCase
{
    public function testCreatesLightWithSafeDefaults(): void
    {
        $result = (new LightPayloadValidator())->create(['x' => 100, 'y' => 200]);

        $this->assertTrue($result['valid']);
        $this->assertSame(200, $result['data']['bright_radius']);
        $this->assertSame(400, $result['data']['dim_radius']);
        $this->assertSame('#FFD27A', $result['data']['color']);
        $this->assertSame(1, $result['data']['gradual_illumination']);
        $this->assertSame('omni', $result['data']['source_type']);
        $this->assertSame(800, $result['data']['lumens']);
        $this->assertSame(0, $result['data']['clarity']);
        $this->assertSame('none', $result['data']['animation']);
    }

    public function testValidatesAdvancedLightingProperties(): void
    {
        $result = (new LightPayloadValidator())->update([
            'revision' => 2,
            'opacity' => 0.7,
            'softness' => 0.4,
            'clarity' => 0.65,
            'darknessMin' => 0.25,
            'darknessMax' => 0.8,
            'sourceType' => 'darkness',
            'providesVision' => true,
            'constrainedByWalls' => false,
            'animation' => 'flicker',
            'animationSpeed' => 2,
            'animationIntensity' => 0.6,
            'elevation' => 3,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('darkness', $result['data']['source_type']);
        $this->assertSame('flicker', $result['data']['animation']);
        $this->assertSame(1, $result['data']['provides_vision']);
        $this->assertSame(0, $result['data']['constrained_by_walls']);
        $this->assertSame(0.65, $result['data']['clarity']);
    }

    public function testValidatesPhotometryAndDirectionalGeometry(): void
    {
        $result = (new LightPayloadValidator())->update([
            'revision' => 3,
            'name' => 'Gate lantern',
            'sourceType' => 'directional',
            'lumens' => 1250,
            'direction' => 275.5,
            'angle' => 70,
            'areaWidth' => 600,
            'areaHeight' => 300,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Gate lantern', $result['data']['name']);
        $this->assertSame('directional', $result['data']['source_type']);
        $this->assertSame(1250, $result['data']['lumens']);
        $this->assertSame(275.5, $result['data']['direction']);
        $this->assertSame(600.0, $result['data']['area_width']);
    }

    public function testRejectsInvertedDarknessRangeAndUnknownAnimation(): void
    {
        $result = (new LightPayloadValidator())->create([
            'x' => 10,
            'y' => 20,
            'darknessMin' => 0.9,
            'darknessMax' => 0.2,
            'animation' => 'rainbow',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('darknessMax', $result['errors']);
        $this->assertArrayHasKey('animation', $result['errors']);
    }

    public function testRejectsClarityOutsideItsNormalizedRange(): void
    {
        $result = (new LightPayloadValidator())->update([
            'revision' => 2,
            'clarity' => 1.01,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('clarity', $result['errors']);
    }

    public function testRejectsInvalidRadiiColorAndOwnedFields(): void
    {
        $result = (new LightPayloadValidator())->create([
            'x' => 10,
            'y' => 20,
            'brightRadius' => 500,
            'dimRadius' => 100,
            'color' => 'red',
            'campaignId' => 7,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('dimRadius', $result['errors']);
        $this->assertArrayHasKey('color', $result['errors']);
        $this->assertArrayHasKey('campaignId', $result['errors']);
    }

    public function testUpdateRequiresRevisionAndWritableField(): void
    {
        $result = (new LightPayloadValidator())->update(['revision' => 1]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }
}
