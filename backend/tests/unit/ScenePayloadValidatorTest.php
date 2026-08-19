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
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Ruins', $result['data']['name']);
        $this->assertSame('hex_pointy', $result['data']['grid_type']);
        $this->assertSame(0, $result['data']['is_visible']);
    }

    public function testUpdateRequiresRevision(): void
    {
        $result = (new ScenePayloadValidator())->validateUpdate(['name' => 'Changed']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
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
