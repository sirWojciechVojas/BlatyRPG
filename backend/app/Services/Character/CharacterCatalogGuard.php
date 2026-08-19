<?php

namespace App\Services\Character;

use CodeIgniter\Database\BaseConnection;

final class CharacterCatalogGuard
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function assertActiveGame(int $systemId, int $universeId): void
    {
        if (!$this->db->table('rpg_systems')->where('id', $systemId)->countAllResults()) {
            throw new CharacterException(
                'validation_failed',
                'Unknown RPG system.',
                422,
                ['systemId' => 'Unknown system.']
            );
        }
        if (!$this->db->table('rpg_universes')->where('id', $universeId)->countAllResults()) {
            throw new CharacterException(
                'validation_failed',
                'Unknown universe.',
                422,
                ['universeId' => 'Unknown universe.']
            );
        }
        $active = $this->db->table('rpg_system_universes')
            ->where('system_id', $systemId)
            ->where('universe_id', $universeId)
            ->where('is_active', 1)
            ->countAllResults();
        if (!$active) {
            throw new CharacterException(
                'validation_failed',
                'Inactive system and universe pair.',
                422,
                ['game' => 'Choose an active system and universe pair.']
            );
        }
    }
}
