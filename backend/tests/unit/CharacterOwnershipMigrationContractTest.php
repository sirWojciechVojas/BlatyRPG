<?php

use App\Database\Migrations\HardenUserRolesAndCharacterOwnership;
use CodeIgniter\Test\CIUnitTestCase;

require_once APPPATH . 'Database/Migrations/'
    . '2026-08-19-154000_HardenUserRolesAndCharacterOwnership.php';

/** @internal */
final class CharacterOwnershipMigrationContractTest extends CIUnitTestCase
{
    public function testGlobalRoleEnumDropsLegacyUserRole(): void
    {
        $contract = HardenUserRolesAndCharacterOwnership::schemaContract();

        $this->assertSame(['player', 'gm', 'admin'], $contract['roles']);
        $this->assertSame('user', $contract['legacyRole']);
        $this->assertNotContains('user', $contract['roles']);
    }

    public function testCharacterOwnershipRelationsHaveSafeDeleteRules(): void
    {
        $relations = HardenUserRolesAndCharacterOwnership::schemaContract()[
            'relations'
        ];

        $this->assertSame('campaigns', $relations['campaign_id']['targetTable']);
        $this->assertSame('CASCADE', $relations['campaign_id']['onDelete']);
        $this->assertSame('users', $relations['user_id']['targetTable']);
        $this->assertSame('SET NULL', $relations['user_id']['onDelete']);
        $this->assertSame('CASCADE', $relations['user_id']['onUpdate']);
        $this->assertNotSame(
            $relations['campaign_id']['index'],
            $relations['user_id']['index']
        );
    }
}
