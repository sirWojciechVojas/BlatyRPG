<?php

namespace App\Services\Campaign;

interface CampaignGameCatalog
{
    public function findActivePair(int $systemId, int $universeId): ?array;

    public function findSingleActivePairBySystemCode(string $systemCode): ?array;
}
