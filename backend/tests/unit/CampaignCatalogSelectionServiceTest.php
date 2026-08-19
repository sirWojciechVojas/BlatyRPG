<?php

use App\Services\Campaign\CampaignCatalogSelectionService;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGameCatalog;
use CodeIgniter\Test\CIUnitTestCase;

final class CampaignGameCatalogStub implements CampaignGameCatalog
{
    public function findActivePair(int $systemId, int $universeId): ?array
    {
        if ($systemId !== 2 || $universeId !== 5) {
            return null;
        }
        return ['system_id' => 2, 'universe_id' => 5, 'system_code' => 'wfrp4e'];
    }

    public function findSingleActivePairBySystemCode(string $systemCode): ?array
    {
        if ($systemCode !== 'wfrp2ed') {
            return null;
        }
        return ['system_id' => 1, 'universe_id' => 3, 'system_code' => 'wfrp2ed'];
    }
}

/** @internal */
final class CampaignCatalogSelectionServiceTest extends CIUnitTestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CampaignCatalogSelectionService(
            new CampaignGameCatalogStub()
        );
    }

    public function testCanonicalPairOverridesLegacySystemCode(): void
    {
        $data = $this->service->forCreate([
            'name' => 'Reikland',
            'system_type' => 'stale',
            'rpg_system_id' => 2,
            'rpg_universe_id' => 5,
        ]);

        $this->assertSame(2, $data['rpg_system_id']);
        $this->assertSame(5, $data['rpg_universe_id']);
        $this->assertSame('wfrp4e', $data['system_type']);
    }

    public function testLegacyCodeResolvesOnlyUnambiguousActivePair(): void
    {
        $data = $this->service->forCreate(['system_type' => 'wfrp2ed']);

        $this->assertSame(1, $data['rpg_system_id']);
        $this->assertSame(3, $data['rpg_universe_id']);
    }

    public function testUnavailablePairFailsClosed(): void
    {
        $this->expectException(CampaignException::class);
        $this->expectExceptionMessage('catalog selection is invalid');

        $this->service->forUpdate([
            'rpg_system_id' => 99,
            'rpg_universe_id' => 88,
        ]);
    }
}
