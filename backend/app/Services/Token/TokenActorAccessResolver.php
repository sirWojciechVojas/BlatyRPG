<?php

namespace App\Services\Token;

use App\Models\ResourcePermissionModel;
use App\Models\ShopOwnerClaimModel;
use App\Services\Authorization\AccessLevel;
use App\Services\Character\CharacterPermissionResolver;
use App\Services\Character\CharacterCampaignAssignmentLookup;
use CodeIgniter\Database\BaseConnection;

final class TokenActorAccessResolver
{
    private $db;
    private $permissions;
    private $levels = [];
    private $characters = [];

    public function __construct(
        ?BaseConnection $db = null,
        ?CharacterPermissionResolver $permissions = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->permissions = $permissions ?: new CharacterPermissionResolver(
            new ResourcePermissionModel($this->db),
            new ShopOwnerClaimModel($this->db)
        );
    }

    public function canControl(array $auth, int $campaignId, array $token): bool
    {
        return AccessLevel::allows(
            $this->level($auth, $campaignId, $token),
            AccessLevel::OWNER
        );
    }

    public function canObserve(array $auth, int $campaignId, array $token): bool
    {
        return AccessLevel::allows(
            $this->level($auth, $campaignId, $token),
            AccessLevel::OBSERVER
        );
    }

    private function level(array $auth, int $campaignId, array $token): string
    {
        $characterId = (int) ($token['character_id'] ?? 0);
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($characterId < 1 || $userId < 1) return AccessLevel::NONE;
        $character = $this->character($characterId);
        if (!$character || !(new CharacterCampaignAssignmentLookup($this->db))
            ->contains($characterId, $campaignId, $character)) {
            return AccessLevel::NONE;
        }
        if ((int) ($character['user_id'] ?? 0) === $userId) return AccessLevel::OWNER;
        $key = $campaignId . ':' . $userId;
        if (!isset($this->levels[$key])) {
            $this->levels[$key] = $this->permissions->levelsFor($userId, $campaignId);
        }
        if (array_key_exists($characterId, $this->levels[$key])) {
            return $this->levels[$key][$characterId];
        }
        return AccessLevel::normalize($character['visibility_level'] ?? null)
            ?: AccessLevel::NONE;
    }

    private function character(int $id): ?array
    {
        if (!array_key_exists($id, $this->characters)) {
            $this->characters[$id] = $this->db->table('characters')
                ->select('id, campaign_id, user_id, visibility_level')
                ->where('id', $id)->get()->getRowArray();
        }
        return $this->characters[$id] ?: null;
    }
}
