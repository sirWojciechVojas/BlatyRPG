<?php

use App\Services\Campaign\CampaignPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignPayloadValidatorTest extends CIUnitTestCase
{
    public function testNormalizesSupportedLegacyPayload(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => '  The Enemy Within  ',
            'description' => '  Weekly game  ',
            'system_type' => 'WFRP2ED',
            'is_active' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('The Enemy Within', $result['data']['name']);
        $this->assertSame('Weekly game', $result['data']['description']);
        $this->assertSame('wfrp2ed', $result['data']['system_type']);
        $this->assertSame(1, $result['data']['is_active']);
    }

    public function testNormalizesDashboardCamelCasePayload(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => 'Middenheim',
            'systemType' => 'WFRP4E',
            'isActive' => false,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('wfrp4e', $result['data']['system_type']);
        $this->assertSame(0, $result['data']['is_active']);
    }

    public function testNormalizesManagedSystemAndWorldIds(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => 'Morrslieb',
            'systemId' => '2',
            'universeId' => 5,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(2, $result['data']['rpg_system_id']);
        $this->assertSame(5, $result['data']['rpg_universe_id']);
    }

    public function testRejectsPartialManagedCatalogSelection(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => 'Incomplete',
            'systemId' => 2,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('universeId', $result['errors']);
    }

    public function testUsesSafeDefaults(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate(['name' => 'New campaign']);

        $this->assertTrue($result['valid']);
        $this->assertSame('wfrp2ed', $result['data']['system_type']);
        $this->assertSame(1, $result['data']['is_active']);
    }

    public function testRejectsIdentityAndOwnershipManipulation(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => 'Injected',
            'game_master_id' => 999,
            'id' => 12,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('game_master_id', $result['errors']);
        $this->assertArrayHasKey('id', $result['errors']);
    }

    public function testRejectsInvalidNameSystemAndBoolean(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => ' ',
            'system_type' => '../unsafe',
            'is_active' => 'sometimes',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('system_type', $result['errors']);
        $this->assertArrayHasKey('is_active', $result['errors']);
    }

    public function testRejectsConflictingAliases(): void
    {
        $result = (new CampaignPayloadValidator())->validateCreate([
            'name' => 'Conflict',
            'system_type' => 'wfrp2ed',
            'systemType' => 'coc7e',
            'is_active' => true,
            'isActive' => false,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('systemType', $result['errors']);
        $this->assertArrayHasKey('isActive', $result['errors']);
    }
}
