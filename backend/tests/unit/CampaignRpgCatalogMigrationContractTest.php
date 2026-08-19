<?php

use CodeIgniter\Test\CIUnitTestCase;

require_once APPPATH
    . 'Database/Migrations/2026-08-19-155000_LinkCampaignsToRpgCatalog.php';

use App\Database\Migrations\LinkCampaignsToRpgCatalog;

/** @internal */
final class CampaignRpgCatalogMigrationContractTest extends CIUnitTestCase
{
    public function testCampaignCatalogRelationsAreIndexed(): void
    {
        $contract = LinkCampaignsToRpgCatalog::schemaContract();

        $this->assertSame('rpg_systems', $contract['rpg_system_id']['target']);
        $this->assertSame('rpg_universes', $contract['rpg_universe_id']['target']);
        $this->assertNotSame(
            $contract['rpg_system_id']['foreignKey'],
            $contract['rpg_universe_id']['foreignKey']
        );
        $this->assertNotEmpty($contract['rpg_system_id']['index']);
        $this->assertNotEmpty($contract['rpg_universe_id']['index']);
    }
}
