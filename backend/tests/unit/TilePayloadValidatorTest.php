<?php

namespace Tests\Unit;

use App\Services\Tile\TilePayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class TilePayloadValidatorTest extends CIUnitTestCase
{
    public function testCreatesImageAndVideoTilesWithSafeAssets(): void
    {
        $validator = new TilePayloadValidator();
        $image = $validator->create(['assetUrl' => '/uploads/maps/crate.webp', 'x' => 20, 'y' => 30]);
        $video = $validator->create([
            'assetUrl' => 'https://cdn.example.test/fire.webm',
            'mediaType' => 'video',
            'layer' => 'foreground',
            'x' => 50,
            'y' => 60,
        ]);

        $this->assertTrue($image['valid']);
        $this->assertSame('image', $image['data']['media_type']);
        $this->assertTrue($video['valid']);
        $this->assertSame('video', $video['data']['media_type']);
    }

    public function testRejectsUnsafeAssetAndInvalidGeometry(): void
    {
        $result = (new TilePayloadValidator())->create([
            'assetUrl' => 'javascript:alert(1)',
            'width' => 2,
            'x' => 0,
            'y' => 0,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('assetUrl', $result['errors']);
        $this->assertArrayHasKey('width', $result['errors']);
    }

    public function testRequiresRevisionAndWritableUpdate(): void
    {
        $result = (new TilePayloadValidator())->update(['opacity' => 0.5]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
    }

    public function testTileServiceKeepsTheLegacyUrlWhileLinkingCentralMedia(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Tile/SceneTileService.php');
        $presenter = file_get_contents(APPPATH . 'Services/Tile/TilePresenter.php');

        $this->assertStringContainsString('registerExternalUrl', $service);
        $this->assertStringContainsString('scene_media_assets', $service);
        $this->assertStringContainsString('map_assets', $service);
        $this->assertStringContainsString("'media_asset_id'", $service);
        $this->assertStringContainsString("'assetUrl' => (string) \$row['asset_url']", $presenter);
        $this->assertStringContainsString("'mediaAssetId'", $presenter);
    }
}
