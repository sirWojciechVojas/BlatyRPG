<?php

use App\Services\Character\CharacterPayloadValidator;
use App\Services\Character\CharacterPresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterRevisionContractTest extends CIUnitTestCase
{
    public function testUpdateAcceptsCanonicalRevisionAndLegacyTimestamp(): void
    {
        $result = (new CharacterPayloadValidator())->validateUpdate([
            'name' => 'Revised hero',
            'revision' => '3',
            'updated_at' => '2026-08-19 12:00:00',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(3, $result['expectedRevision']);
        $this->assertSame('2026-08-19 12:00:00', $result['expectedUpdatedAt']);
    }

    public function testInvalidOrConflictingRevisionIsRejected(): void
    {
        $validator = new CharacterPayloadValidator();
        $invalid = $validator->validateUpdate(['name' => 'Hero', 'revision' => 0]);
        $conflict = $validator->validateUpdate([
            'name' => 'Hero',
            'revision' => 2,
            'expected_revision' => 3,
        ]);

        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('revision', $invalid['errors']);
        $this->assertFalse($conflict['valid']);
        $this->assertArrayHasKey('revision', $conflict['errors']);
    }

    public function testCreatePreservesOptionalLegacyAssetSetAlias(): void
    {
        $result = (new CharacterPayloadValidator())->validateCreate([
            'campaign_id' => 1,
            'system_id' => 2,
            'universe_id' => 3,
            'name' => 'Legacy hero',
            'asset_set_id' => '7',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(7, $result['assetSetId']);
    }

    public function testPresenterExposesRevisionAndSafeLegacyAliases(): void
    {
        $result = CharacterPresenter::present([
            'id' => 4,
            'campaign_id' => 2,
            'system_id' => 1,
            'universe_id' => 3,
            'name' => 'Roch',
            'revision' => '6',
            '_permissions' => ['canEdit' => true, 'accessLevel' => 'owner'],
        ]);

        $this->assertSame(6, $result['revision']);
        $this->assertSame(2, $result['campaign_id']);
        $this->assertSame(1, $result['system_id']);
    }
}
