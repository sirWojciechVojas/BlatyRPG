<?php

use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AdminMediaLibraryContractTest extends CIUnitTestCase
{
    public function testAdminRoutesCoverLibraryCollectionsReplacementAndPurgeRetry(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        foreach ([
            'admin/media-assets', 'admin/media-assets/bulk',
            'admin/media-assets/character-sets', '/replacement', '/retry-purge',
            'admin/media-assets/external', 'admin/media-collections', '/assets',
        ] as $route) {
            $this->assertStringContainsString($route, $routes);
        }
    }

    public function testServiceUsesRealRelationsAndBlocksPurgeBeforeProviderDeletion(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Admin/AdminMediaLibraryService.php');
        $resolver = file_get_contents(APPPATH . 'Services/Admin/MediaAssetRelationshipResolver.php');
        $this->assertStringContainsString("'asset_in_use'", $service);
        $this->assertStringContainsString('$this->relations->forAsset($assetId)', $service);
        $this->assertStringContainsString('$this->media->purge($assetId)', $service);
        $this->assertLessThan(
            strpos($service, '$this->media->purge($assetId)'),
            strpos($service, '$this->relations->forAsset($assetId)')
        );
        $this->assertStringNotContainsString('polymorphic', $resolver);
        $this->assertStringContainsString("'character_assets'", $resolver);
        $this->assertStringContainsString("'scene_media_assets'", $resolver);
        $this->assertStringContainsString("'scene_tiles'", $resolver);
    }

    public function testProtectedCloudinaryDeliveryUsesExpiringAssetDownload(): void
    {
        $source = file_get_contents(APPPATH . 'Services/Media/CloudinaryMediaProvider.php');
        $this->assertStringContainsString('/asset/download?', $source);
        $this->assertStringContainsString("'expires_at'", $source);
        $this->assertStringContainsString("'asset_id'", $source);
    }

    public function testPersonalAudioTrackCanBeCopiedOrMovedToASettingLibrary(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Admin/AdminMediaLibraryService.php');
        $controller = file_get_contents(APPPATH . 'Controllers/Api/AdminMediaController.php');
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString('publishPersonalAudioTrack', $service);
        $this->assertStringContainsString("['copy', 'move']", $service);
        $this->assertStringContainsString('audioTrackCopy', $service);
        $this->assertStringContainsString('audioLibraries', $service);
        $this->assertStringContainsString("'perPage' => 25", $service);
        $this->assertStringContainsString('publishPersonalAudioTrack', $controller);
        $this->assertStringContainsString('media-audio-libraries', $routes);
        $this->assertStringContainsString('admin/media-assets/audio-tracks/(:num)/publish', $routes);
    }
}
