<?php

namespace App\Services\Campaign;

final class CampaignCatalogSelectionService
{
    private $catalog;

    public function __construct(?CampaignGameCatalog $catalog = null)
    {
        $this->catalog = $catalog ?: new DatabaseCampaignGameCatalog();
    }

    public function forCreate(array $data): array
    {
        if ($this->hasIds($data)) {
            return $this->applyPair($data, $this->pairFromIds($data));
        }

        $systemCode = (string) ($data['system_type'] ?? 'wfrp2ed');
        $pair = $this->catalog->findSingleActivePairBySystemCode($systemCode);
        if (!$pair) {
            throw $this->invalid(
                'systemId',
                'Choose an active RPG system and world combination.'
            );
        }

        return $this->applyPair($data, $pair);
    }

    public function forUpdate(array $data): array
    {
        if (!$this->hasIds($data)) {
            return $data;
        }

        return $this->applyPair($data, $this->pairFromIds($data));
    }

    private function pairFromIds(array $data): array
    {
        $pair = $this->catalog->findActivePair(
            (int) $data['rpg_system_id'],
            (int) $data['rpg_universe_id']
        );
        if (!$pair) {
            throw $this->invalid(
                'universeId',
                'The selected RPG system and world combination is unavailable.'
            );
        }
        return $pair;
    }

    private function applyPair(array $data, array $pair): array
    {
        $data['rpg_system_id'] = (int) $pair['system_id'];
        $data['rpg_universe_id'] = (int) $pair['universe_id'];
        $data['system_type'] = strtolower((string) $pair['system_code']);
        return $data;
    }

    private function hasIds(array $data): bool
    {
        return isset($data['rpg_system_id'], $data['rpg_universe_id']);
    }

    private function invalid(string $field, string $message): CampaignException
    {
        return new CampaignException(
            'validation_failed',
            'Campaign RPG catalog selection is invalid.',
            422,
            [$field => $message]
        );
    }
}
