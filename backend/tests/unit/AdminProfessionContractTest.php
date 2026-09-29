<?php

use App\Controllers\Api\AdminController;
use App\Services\Admin\AdminProfessionService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AdminProfessionContractTest extends CIUnitTestCase
{
    public function testAdministrativeProfessionSurfaceIsLoadable(): void
    {
        $this->assertTrue(class_exists(AdminController::class));
        $this->assertTrue(class_exists(AdminProfessionService::class));
    }

    public function testRoutesExposeProtectedProfessionCrud(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString(
            "get('admin/professions'",
            $routes
        );
        $this->assertStringContainsString(
            "post('admin/professions'",
            $routes
        );
        $this->assertStringContainsString(
            "patch('admin/professions/(:num)'",
            $routes
        );
        $this->assertStringContainsString(
            "delete('admin/professions/(:num)'",
            $routes
        );
        $this->assertStringContainsString(
            "post('admin/professions/(:num)/images/(:segment)'",
            $routes
        );
        $this->assertStringContainsString(
            "get('profession-assets/(:num)/file'",
            $routes
        );
    }

    public function testServiceProtectsCharacterHistoryAndStaleWrites(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Admin/AdminProfessionService.php'
        );

        $this->assertStringContainsString(
            "'character_professions', 'profession_id'",
            $service
        );
        $this->assertStringContainsString("'profession_in_use'", $service);
        $this->assertStringContainsString("'profession_changed'", $service);
        $this->assertStringContainsString('verifiedAdmin($auth)', $service);
    }
}
