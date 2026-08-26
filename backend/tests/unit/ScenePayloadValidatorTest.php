<?php

use App\Services\Scene\ScenePayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ScenePayloadValidatorTest extends CIUnitTestCase
{
    public function testAcceptsSupportedGridAndNormalizesPayload(): void
    {
        $result = (new ScenePayloadValidator())->validateCreate([
            'name' => '  Ruins  ',
            'width' => 4096,
            'height' => 2048,
            'grid_type' => 'HEX_POINTY',
            'grid_opacity' => 0.5,
            'is_visible' => false,
            'global_illumination' => true,
            'fog_exploration' => false,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Ruins', $result['data']['name']);
        $this->assertSame('hex_pointy', $result['data']['grid_type']);
        $this->assertSame(0, $result['data']['is_visible']);
        $this->assertSame(1, $result['data']['global_illumination']);
        $this->assertSame(0, $result['data']['fog_exploration']);
    }

    public function testUpdateRequiresRevision(): void
    {
        $result = (new ScenePayloadValidator())->validateUpdate(['name' => 'Changed']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
    }

    public function testGlobalLightLevelReplacesAndMirrorsLegacyDarkness(): void
    {
        $result = (new ScenePayloadValidator())->validateUpdate([
            'revision' => 2,
            'global_light_level' => 0.65,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(0.65, $result['data']['global_light_level']);
        $this->assertEqualsWithDelta(0.35, $result['data']['darkness_level'], 0.0001);
        $this->assertSame(0, $result['data']['global_illumination']);
    }

    public function testLegacyDarknessStillUpdatesGlobalIllumination(): void
    {
        $result = (new ScenePayloadValidator())->validateUpdate([
            'revision' => 2,
            'darkness_level' => 0.5,
            'global_illumination' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertEqualsWithDelta(0.91, $result['data']['global_light_level'], 0.0001);
    }

    public function testRejectsUnsafeAssetUrlAndUnknownFields(): void
    {
        $result = (new ScenePayloadValidator())->validateCreate([
            'name' => 'Unsafe',
            'background_url' => 'javascript:alert(1)',
            'campaign_id' => 99,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('background_url', $result['errors']);
        $this->assertArrayHasKey('campaign_id', $result['errors']);
    }
}
