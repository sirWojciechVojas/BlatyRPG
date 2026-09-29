<?php

namespace App\Services\Admin;

use CodeIgniter\Database\BaseConnection;

final class AdminCharacterDirectory
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function all(): array
    {
        return self::assemble(
            $this->characterRows(),
            $this->campaignRows(),
            $this->ownerRows(),
            $this->gameMasterRows()
        );
    }

    public static function assemble(
        array $characters,
        array $campaigns,
        array $owners,
        array $gameMasters
    ): array {
        return [
            'characters' => self::characters($characters),
            'characterCampaigns' => self::assignments($campaigns, false),
            'characterOwners' => self::assignments($owners, true),
            'characterGameMasters' => self::gameMasters($gameMasters),
        ];
    }

    private function characterRows(): array
    {
        return $this->db->table('characters characters')
            ->select('characters.id, characters.name, characters.updated_at')
            ->orderBy('characters.name', 'ASC')
            ->get()->getResultArray();
    }

    private function campaignRows(): array
    {
        if (!$this->db->tableExists('character_campaigns')) {
            return $this->db->table('characters characters')
                ->select('characters.id AS character_id, characters.campaign_id, '
                    . 'campaigns.name AS campaign_name')
                ->join('campaigns campaigns', 'campaigns.id = characters.campaign_id', 'inner')
                ->where('campaigns.deleted_at', null)->get()->getResultArray();
        }
        return $this->db->table('character_campaigns assignments')
            ->select('assignments.character_id, assignments.campaign_id, '
                . 'campaigns.name AS campaign_name')
            ->join('campaigns campaigns', 'campaigns.id = assignments.campaign_id', 'inner')
            ->where('campaigns.deleted_at', null)
            ->orderBy('campaigns.name', 'ASC')->get()->getResultArray();
    }

    private function ownerRows(): array
    {
        return $this->db->table('resource_permissions permissions')
            ->select('permissions.resource_id AS character_id, permissions.campaign_id, '
                . 'permissions.user_id, users.username, campaigns.name AS campaign_name')
            ->join('users users', 'users.id = permissions.user_id', 'inner')
            ->join('campaigns campaigns', 'campaigns.id = permissions.campaign_id', 'inner')
            ->join('campaign_members members', 'members.campaign_id = permissions.campaign_id '
                . 'AND members.user_id = permissions.user_id', 'inner')
            ->where('permissions.resource_type', 'character')
            ->where('permissions.access_level', 'owner')
            ->where('members.role', 'gm')->where('members.is_active', 1)
            ->where('users.deleted_at', null)->where('campaigns.deleted_at', null)
            ->orderBy('users.username', 'ASC')->get()->getResultArray();
    }

    private function gameMasterRows(): array
    {
        return $this->db->table('campaign_members members')
            ->select(
                'members.campaign_id, members.user_id, users.username, '
                . 'campaigns.game_master_id'
            )
            ->join('campaigns campaigns', 'campaigns.id = members.campaign_id', 'inner')
            ->join('users users', 'users.id = members.user_id', 'inner')
            ->where('members.role', 'gm')
            ->where('members.is_active', 1)
            ->where('campaigns.deleted_at', null)
            ->where('users.deleted_at', null)
            ->orderBy('members.campaign_id', 'ASC')
            ->orderBy('users.username', 'ASC')
            ->get()->getResultArray();
    }

    private static function characters(array $rows): array
    {
        return array_values(array_filter(array_map(static fn (array $row): array => [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'updatedAt' => $row['updated_at'] ?? null,
        ], $rows), static fn (array $item): bool => $item['id'] > 0));
    }

    private static function assignments(array $rows, bool $withOwner): array
    {
        $items = [];
        foreach ($rows as $row) {
            $item = [
                'characterId' => (int) ($row['character_id'] ?? 0),
                'campaignId' => (int) ($row['campaign_id'] ?? 0),
                'campaignName' => (string) ($row['campaign_name'] ?? ''),
            ];
            if ($withOwner) {
                $item['userId'] = (int) ($row['user_id'] ?? 0);
                $item['username'] = (string) ($row['username'] ?? '');
            }
            if ($item['characterId'] > 0 && $item['campaignId'] > 0) {
                $items[] = $item;
            }
        }
        return $items;
    }

    private static function gameMasters(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            $campaignId = (int) ($row['campaign_id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            if ($campaignId > 0 && $userId > 0) {
                $items[$campaignId . ':' . $userId] = [
                    'campaignId' => $campaignId,
                    'userId' => $userId,
                    'username' => (string) ($row['username'] ?? ''),
                    'isCampaignOwner' => $userId === (int) ($row['game_master_id'] ?? 0),
                ];
            }
        }
        return array_values($items);
    }
}
