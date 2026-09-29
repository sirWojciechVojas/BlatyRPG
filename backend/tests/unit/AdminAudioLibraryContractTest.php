<?php

use App\Services\Admin\AdminAudioLibraryService;
use App\Services\Audio\AudioLibraryService;
use CodeIgniter\Test\CIUnitTestCase;

final class AdminAudioLibraryContractTest extends CIUnitTestCase
{
    public function testAdministratorAndCampaignAudioSurfacesAreLoadable(): void
    {
        $this->assertTrue(class_exists(AdminAudioLibraryService::class));
        $this->assertTrue(class_exists(AudioLibraryService::class));
    }

    public function testCatalogSeparatesSettingAndPersonalLibraries(): void
    {
        $campaignService = file_get_contents(
            APPPATH . 'Services/Audio/AudioLibraryService.php'
        );
        $adminService = file_get_contents(
            APPPATH . 'Services/Admin/AdminAudioLibraryService.php'
        );
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString("'setting' => ", $campaignService);
        $this->assertStringContainsString("'personal' => ", $campaignService);
        $this->assertStringContainsString('function personalRows', $campaignService);
        $this->assertStringContainsString('function attachTrack', $campaignService);
        $this->assertStringContainsString('function updatePersonalTrack', $campaignService);
        $this->assertStringContainsString("array_key_exists('url', \$payload)", $campaignService);
        $this->assertStringContainsString("'scope' => 'system'", $adminService);
        $this->assertStringContainsString("table('rpg_system_universes')", $adminService);
        $this->assertStringContainsString('storeForLibrary', $adminService);
        $this->assertStringContainsString("get('admin/audio'", $routes);
        $this->assertStringContainsString("post('admin/audio/libraries'", $routes);
        $this->assertStringContainsString(
            "patch('campaigns/(:num)/audio/library/tracks/(:num)'",
            $routes
        );
    }
}
