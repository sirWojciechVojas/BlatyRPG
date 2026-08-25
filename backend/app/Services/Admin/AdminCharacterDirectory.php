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
        return self::assemble($this->characterRows(), $this->gameMasterRows());
    }

    public static function assemble(array $characters, array $gameMasters): array
    {
        $items = array_map(static function (array $row): array {
            $campaignId = (int) ($row['campaign_id'] ?? 0);
            $ownerId = (int) ($row['owner_id'] ?? 0);
            return [
                'id' => (int) ($row['id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'campaignId' => $campaignId > 0 ? $campaignId : null,
                'campaignName' => $row['campaign_name'] ?? null,
                'ownerId' => $ownerId > 0 ? $ownerId : null,
                'ownerName' => $row['owner_name'] ?? null,
                'updatedAt' => $row['updated_at'] ?? null,
            ];
        }, $characters);

        $candidates = [];
        foreach ($gameMasters as $row) {
            $campaignId = (int) ($row['campaign_id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            if ($campaignId < 1 || $userId < 1) {
                continue;
            }
            $key = $campaignId . ':' . $userId;
            $candidates[$key] = [
                'campaignId' => $campaignId,
                'userId' => $userId,
                'username' => (string) ($row['username'] ?? ''),
                'isCampaignOwner' => $userId === (int) ($row['game_master_id'] ?? 0),
            ];
        }
        $candidates = array_values($candidates);
        usort($candidates, static function (array $left, array $right): int {
            if ($left['campaignId'] !== $right['campaignId']) {
                return $left['campaignId'] <=> $right['campaignId'];
            }
            if ($left['isCampaignOwner'] !== $right['isCampaignOwner']) {
                return $left['isCampaignOwner'] ? -1 : 1;
            }
            return strcasecmp($left['username'], $right['username']);
        });

        return [
            'characters' => array_values(array_filter(
                $items,
                static fn (array $item): bool => $item['id'] > 0
            )),
            'characterGameMasters' => $candidates,
        ];
    }

    private function characterRows(): array
    {
        return $this->db->table('characters characters')
            ->select(
                'characters.id, characters.name, characters.campaign_id, '
                . 'characters.user_id AS owner_id, characters.updated_at, '
                . 'campaigns.name AS campaign_name, owner.username AS owner_name'
            )
            ->join('campaigns campaigns', 'campaigns.id = characters.campaign_id', 'left')
            ->join(
                'users owner',
                'owner.id = characters.user_id AND owner.deleted_at IS NULL',
                'left'
            )
            ->groupStart()
                ->where('campaigns.deleted_at', null)
                ->orWhere('campaigns.id', null)
            ->groupEnd()
            ->orderBy('campaigns.name', 'ASC')
            ->orderBy('characters.name', 'ASC')
            ->get()->getResultArray();
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
}
