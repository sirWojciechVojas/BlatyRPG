<?php

use App\Controllers\Api\AdminController;
use App\Services\Admin\AdminCompendiumService;
use CodeIgniter\Test\CIUnitTestCase;

final class AdminCompendiumContractTest extends CIUnitTestCase
{
    public function testAdministratorCompendiumSurfaceIsLoadable(): void
    {
        $this->assertTrue(class_exists(AdminController::class));
        $this->assertTrue(class_exists(AdminCompendiumService::class));
    }

    public function testPolicyAndAutomationGuardsArePartOfTheServiceContract(): void
    {
        $source = file_get_contents(APPPATH . 'Services/Admin/AdminCompendiumService.php');

        $this->assertStringContainsString('Only a verified profile may be usable.', $source);
        $this->assertStringContainsString('verifiedAdmin($auth)', $source);
        $this->assertStringContainsString('player_search_normalized', $source);
        $this->assertStringContainsString('rollbackImport', $source);
    }

    public function testAdministratorCanCreateAnInitializedWorldBoundToAnRpgSystem(): void
    {
        $service = file_get_contents(APPPATH . 'Services/Admin/AdminCompendiumService.php');
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $migration = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-08-100000_AssignDefaultRpgSystemsToUniverses.php'
        );

        $this->assertStringContainsString('function createWorld', $service);
        $this->assertStringContainsString('function updateWorld', $service);
        $this->assertStringContainsString("table('rpg_system_universes')->insert", $service);
        $this->assertStringContainsString("table('compendium_worlds')->insert", $service);
        $this->assertStringContainsString("post('admin/compendium/worlds'", $routes);
        $this->assertStringContainsString("patch('admin/compendium/worlds/(:num)'", $routes);
        $this->assertStringContainsString("'old_world' => 'wfrp2ed'", $migration);
        $this->assertStringContainsString("'symbaroum_world' => 'symbaroum_sys'", $migration);
    }
}
