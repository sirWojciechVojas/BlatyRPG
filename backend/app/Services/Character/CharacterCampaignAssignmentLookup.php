<?php

namespace App\Services\Character;

use CodeIgniter\Database\BaseConnection;

final class CharacterCampaignAssignmentLookup
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function idsForCampaign(int $campaignId): array
    {
        if (!$this->db->tableExists('character_campaigns')) {
            $rows = $this->db->table('characters')->select('id')
                ->where('campaign_id', $campaignId)->get()->getResultArray();
        } else {
            $rows = $this->db->table('character_campaigns')->select('character_id AS id')
                ->where('campaign_id', $campaignId)->get()->getResultArray();
        }
        return array_values(array_filter(array_map(
            static fn (array $row): int => (int) ($row['id'] ?? 0),
            $rows
        )));
    }

    public function contains(int $characterId, int $campaignId, array $character = []): bool
    {
        if ($characterId < 1 || $campaignId < 1) {
            return false;
        }
        if ($this->db->tableExists('character_campaigns')) {
            return $this->db->table('character_campaigns')
                ->where('character_id', $characterId)
                ->where('campaign_id', $campaignId)->countAllResults() > 0;
        }
        return (int) ($character['campaign_id'] ?? 0) === $campaignId;
    }

    public function attachCreated(int $characterId, int $campaignId, int $actorId): void
    {
        if (!$this->db->tableExists('character_campaigns')) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        if (!$this->db->table('character_campaigns')->insert([
            'character_id' => $characterId,
            'campaign_id' => $campaignId,
            'assigned_by_user_id' => $actorId,
            'created_at' => $now,
            'updated_at' => $now,
        ])) {
            throw new CharacterException(
                'character_write_failed',
                'Character campaign assignment could not be saved.',
                500
            );
        }
    }
}
