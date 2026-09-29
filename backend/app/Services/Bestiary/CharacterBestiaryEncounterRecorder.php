<?php

namespace App\Services\Bestiary;

use CodeIgniter\Database\BaseConnection;

/** Persists encounters only after the normal token ACL and fog filters ran. */
final class CharacterBestiaryEncounterRecorder
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function recordVisibleTokens(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $visibleTokens,
        bool $canManage
    ): void {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($canManage || $userId < 1 || !$visibleTokens || !$this->schemaReady()) {
            return;
        }

        $tokenIds = $this->positiveIds(array_column($visibleTokens, 'id'));
        $visibleCharacterIds = $this->positiveIds(
            array_column($visibleTokens, 'character_id')
        );
        if (!$tokenIds || !$visibleCharacterIds) {
            return;
        }

        $heroIds = $this->ownedCharacters(
            $campaignId,
            $userId,
            $visibleCharacterIds
        );
        if (!$heroIds) {
            return;
        }

        $builder = $this->db->table('compendium_materializations materialized')
            ->distinct()
            ->select(
                'version.entry_id, materialized.token_id, '
                . 'materialized.character_id'
            )
            ->join(
                'compendium_entry_versions version',
                'version.id = materialized.version_id',
                'inner'
            )
            ->join(
                'compendium_entries entry',
                'entry.id = version.entry_id',
                'inner'
            )
            ->join(
                'compendium_entry_types entry_type',
                'entry_type.id = version.type_id',
                'inner'
            )
            ->where('materialized.campaign_id', $campaignId)
            ->where('entry.status', 'active')
            ->where('entry.deleted_at', null)
            ->where('entry_type.code', 'creature')
            ->groupStart()
            ->whereIn('materialized.token_id', $tokenIds)
            ->orWhereIn('materialized.character_id', $visibleCharacterIds)
            ->groupEnd();
        $creatures = $builder->get()->getResultArray();
        if (!$creatures) {
            return;
        }

        $grants = [];
        $entryIds = $this->positiveIds(array_column($creatures, 'entry_id'));
        $grantRows = $this->db
            ->table('compendium_campaign_reveals reveal_row')
            ->distinct()
            ->select('reveal_row.character_id, entity.entry_id')
            ->join(
                'compendium_entities entity',
                'entity.id=reveal_row.entity_id AND entity.deleted_at IS NULL',
                'inner'
            )
            ->where('reveal_row.campaign_id', $campaignId)
            ->where('reveal_row.revoked_at', null)
            ->whereIn('reveal_row.character_id', $heroIds)
            ->whereIn('entity.entry_id', $entryIds)
            ->get()
            ->getResultArray();
        foreach ($grantRows as $grant) {
            $grants[(int) $grant['character_id']][(int) $grant['entry_id']] = true;
        }

        $visibleTokenByCharacter = [];
        foreach ($visibleTokens as $token) {
            $characterId = (int) ($token['character_id'] ?? 0);
            if ($characterId > 0 && !isset($visibleTokenByCharacter[$characterId])) {
                $visibleTokenByCharacter[$characterId] = (int) $token['id'];
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($heroIds as $heroId) {
            foreach ($creatures as $creature) {
                $entryId = (int) $creature['entry_id'];
                if (empty($grants[$heroId][$entryId])) {
                    continue;
                }
                $sourceTokenId = (int) ($creature['token_id'] ?? 0);
                if (!in_array($sourceTokenId, $tokenIds, true)) {
                    $sourceTokenId = $visibleTokenByCharacter[
                        (int) ($creature['character_id'] ?? 0)
                    ] ?? 0;
                }
                $this->db->table('character_bestiary_encounters')
                    ->ignore(true)
                    ->insert([
                        'campaign_id' => $campaignId,
                        'character_id' => $heroId,
                        'entry_id' => $entryId,
                        'first_seen_scene_id' => $sceneId,
                        'first_seen_token_id' => $sourceTokenId ?: null,
                        'discovered_at' => $now,
                    ]);
                $this->db->table('character_bestiary_knowledge')
                    ->where('campaign_id', $campaignId)
                    ->where('character_id', $heroId)
                    ->where('entry_id', $entryId)
                    ->update([
                        'knowledge_level' => 'full',
                        'updated_at' => $now,
                    ]);
                if ($this->db->affectedRows() === 0) {
                    $this->db->table('character_bestiary_knowledge')
                        ->ignore(true)
                        ->insert([
                            'campaign_id' => $campaignId,
                            'character_id' => $heroId,
                            'entry_id' => $entryId,
                            'knowledge_level' => 'full',
                            'updated_by_user_id' => null,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                }
            }
        }
    }

    private function ownedCharacters(
        int $campaignId,
        int $userId,
        array $candidateIds
    ): array {
        $userIdSql = (int) $userId;
        $campaignIdSql = (int) $campaignId;
        $rows = $this->db->table('characters character_row')
            ->distinct()
            ->select('character_row.id')
            ->join(
                'character_campaigns assignment',
                'assignment.character_id = character_row.id '
                . "AND assignment.campaign_id = {$campaignIdSql}",
                'left'
            )
            ->whereIn('character_row.id', $candidateIds)
            ->groupStart()
            ->where('character_row.campaign_id', $campaignId)
            ->orWhere('assignment.campaign_id', $campaignId)
            ->groupEnd()
            ->groupStart()
            ->where('character_row.user_id', $userId)
            ->orWhere(
                'EXISTS (SELECT 1 FROM resource_permissions permission '
                . 'WHERE permission.campaign_id = ' . $campaignIdSql
                . " AND permission.resource_type = 'character'"
                . ' AND permission.resource_id = character_row.id'
                . ' AND permission.user_id = ' . $userIdSql
                . " AND permission.access_level = 'owner')",
                null,
                false
            )
            ->groupEnd()
            ->get()
            ->getResultArray();

        return $this->positiveIds(array_column($rows, 'id'));
    }

    private function positiveIds(array $values): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $values),
            static fn (int $id): bool => $id > 0
        )));
    }

    private function schemaReady(): bool
    {
        return $this->db->tableExists('character_bestiary_encounters')
            && $this->db->tableExists('character_bestiary_knowledge')
            && $this->db->tableExists('compendium_materializations')
            && $this->db->fieldExists(
                'character_id',
                'compendium_campaign_reveals'
            );
    }
}
