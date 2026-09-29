<?php

use App\Database\Migrations\SeparateAccountAndCampaignRoles;
use CodeIgniter\Test\CIUnitTestCase;

require_once APPPATH . 'Database/Migrations/2026-08-23-220000_SeparateAccountAndCampaignRoles.php';

final class AccountRoleMigrationContractTest extends CIUnitTestCase
{
    public function testAccountAndCampaignRolesStaySeparate(): void
    {
        $contract = SeparateAccountAndCampaignRoles::schemaContract();

        $this->assertSame(['user', 'admin'], $contract['accountRoles']);
        $this->assertSame(['player', 'gm'], $contract['legacyAccountRoles']);
    }
}
