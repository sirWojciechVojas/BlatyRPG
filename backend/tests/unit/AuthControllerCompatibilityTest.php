<?php

use App\Controllers\Api\AuthController;
use App\Controllers\Api\AuthSessionController;
use CodeIgniter\Test\CIUnitTestCase;

final class AuthControllerCompatibilityTest extends CIUnitTestCase
{
    public function testControllersCanBeLoadedWithFrameworkBaseClasses(): void
    {
        $this->assertTrue(class_exists(AuthController::class));
        $this->assertTrue(class_exists(AuthSessionController::class));
    }
}
