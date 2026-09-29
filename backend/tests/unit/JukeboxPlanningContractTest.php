<?php

use App\Services\Audio\JukeboxPlanningService;
use CodeIgniter\Test\CIUnitTestCase;

final class JukeboxPlanningContractTest extends CIUnitTestCase
{
    public function testPlanningServiceAndPersistentSchemaAreAvailable(): void
    {
        $this->assertTrue(class_exists(JukeboxPlanningService::class));

        $migration = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-13-120000_CreateJukeboxPlaylistsAndQueues.php'
        );
        $this->assertStringContainsString('audio_playlists', $migration);
        $this->assertStringContainsString('audio_playlist_items', $migration);
        $this->assertStringContainsString('campaign_jukebox_queue_items', $migration);
        $this->assertStringContainsString(
            "addKey(['campaign_id', 'channel_id', 'sort_order', 'id'])",
            $migration
        );
    }

    public function testCampaignRoutesExposeRealPlaylistAndQueueOperations(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $service = file_get_contents(APPPATH . 'Services/Audio/JukeboxPlanningService.php');

        $this->assertStringContainsString("audio/playlists'", $routes);
        $this->assertStringContainsString('audio/queues/(:segment)/tracks', $routes);
        $this->assertStringContainsString('function startPlaylist', $service);
        $this->assertStringContainsString('function moveQueueItem', $service);
        $this->assertStringContainsString('function clearQueue', $service);
        $this->assertStringContainsString('JukeboxStateService::CHANNELS', $service);
    }
}
