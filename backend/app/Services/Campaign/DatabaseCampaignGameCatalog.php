<?php

namespace App\Services\Campaign;

use CodeIgniter\Database\BaseConnection;

final class DatabaseCampaignGameCatalog implements CampaignGameCatalog
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function findActivePair(int $systemId, int $universeId): ?array
    {
        $row = $this->baseQuery()
            ->where('pair.system_id', $systemId)
            ->where('pair.universe_id', $universeId)
            ->where('pair.is_active', 1)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function findSingleActivePairBySystemCode(string $systemCode): ?array
    {
        $rows = $this->baseQuery()
            ->where('systems.code', $systemCode)
            ->where('pair.is_active', 1)
            ->limit(2)
            ->get()
            ->getResultArray();

        return count($rows) === 1 ? $rows[0] : null;
    }

    private function baseQuery()
    {
        return $this->db->table('rpg_system_universes pair')
            ->select([
                'pair.system_id',
                'pair.universe_id',
                'systems.code AS system_code',
            ])
            ->join('rpg_systems systems', 'systems.id = pair.system_id')
            ->join('rpg_universes universes', 'universes.id = pair.universe_id');
    }
}
