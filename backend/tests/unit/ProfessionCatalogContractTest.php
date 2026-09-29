<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class ProfessionCatalogContractTest extends CIUnitTestCase
{
    public function testRoutesExposeCampaignSystemAndCharacterReads(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString(
            "get('campaigns/(:num)/professions'",
            $routes
        );
        $this->assertStringContainsString(
            "get('systems/(:num)/professions'",
            $routes
        );
        $this->assertStringContainsString(
            "characters/(:num)/professions'",
            $routes
        );
        $this->assertStringContainsString(
            "put('campaigns/(:num)/characters/(:num)/profession'",
            $routes
        );
        $this->assertStringContainsString(
            "professions/history-order'",
            $routes
        );
        $this->assertStringContainsString(
            "professions/(:num)/activate'",
            $routes
        );
        $this->assertStringContainsString(
            "delete('campaigns/(:num)/characters/(:num)/professions/(:num)'",
            $routes
        );
    }

    public function testCatalogKeepsEveryRecordAndDoesNotFilterIsMain(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Profession/ProfessionCatalogService.php'
        );

        $this->assertStringContainsString(
            "->where('system_id', \$systemId)",
            $service
        );
        $this->assertStringNotContainsString(
            "->where('is_main'",
            $service
        );
        $this->assertStringContainsString("'is_main' =>", $service);
        $this->assertStringContainsString("'rules_available' => false", $service);
    }

    public function testCharacterHistoryUsesExplicitStatusAndStableIdentifiers(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Profession/ProfessionCatalogService.php'
        );

        $this->assertStringContainsString('cp.profession_id', $service);
        $this->assertStringContainsString('cp.is_finished', $service);
        $this->assertStringContainsString("'professionId' =>", $service);
        $this->assertStringContainsString("'isFinished' =>", $service);
        $this->assertStringContainsString("'canManageProfession' =>", $service);
        $this->assertStringContainsString("'xpSpent' => 0", $service);
        $this->assertStringContainsString(
            "->join('professions p', 'p.id=cp.profession_id', 'left')",
            $service
        );
    }
}
