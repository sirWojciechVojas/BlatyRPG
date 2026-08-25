<?php

namespace App\Services\Token;

use App\Services\Character\CharacterCampaignAssignmentLookup;
use CodeIgniter\Database\BaseConnection;

final class TokenAccessService
{
    private $db;
    private $actors;

    public function __construct(
        ?BaseConnection $db = null,
        ?TokenActorAccessResolver $actors = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->actors = $actors ?: new TokenActorAccessResolver($this->db);
    }

    public function canView(array $auth, int $campaignId, array $token, bool $canManage): bool
    {
        if ($canManage) return true;
        if (!empty($token['hidden'])) return false;
        return $this->allows(
            $token,
            'visible_to_json',
            'everyone',
            (int) ($auth['user_id'] ?? 0),
            true
        );
    }

    public function canControl(array $auth, int $campaignId, array $token, bool $canManage): bool
    {
        if ($canManage) return true;
        $scope = TokenPermissionScope::stored(
            $token['controlled_by_json'] ?? null,
            'inherit'
        );
        $inherited = $scope['mode'] === TokenPermissionScope::INHERIT
            && $this->actors->canControl($auth, $campaignId, $token);
        return TokenPermissionScope::allows(
            $scope, (int) ($auth['user_id'] ?? 0), $inherited
        );
    }

    public function canEdit(array $auth, int $campaignId, array $token, bool $canManage): bool
    {
        if ($canManage) return true;
        return $this->allows(
            $token,
            'editable_by_json',
            'gm',
            (int) ($auth['user_id'] ?? 0),
            false
        );
    }

    public function canObserve(array $auth, int $campaignId, array $token, bool $canManage): bool
    {
        if ($canManage) return true;
        $scope = TokenPermissionScope::stored(
            $token['observer_by_json'] ?? null,
            'inherit'
        );
        $inherited = $scope['mode'] === TokenPermissionScope::INHERIT
            && $this->actors->canObserve($auth, $campaignId, $token);
        return TokenPermissionScope::allows(
            $scope, (int) ($auth['user_id'] ?? 0), $inherited
        );
    }

    public function assertCharacterInCampaign(int $campaignId, ?int $characterId): void
    {
        if ($characterId === null) return;
        $row = $this->db->table('characters')->select('id, campaign_id')
            ->where('id', $characterId)->get()->getRowArray();
        if (!$row || !(new CharacterCampaignAssignmentLookup($this->db))
            ->contains($characterId, $campaignId, $row)) {
            throw new TokenException(
                'character_not_found',
                'Linked character was not found in this campaign.',
                422,
                ['characterId' => 'Choose a character from this campaign.']
            );
        }
    }

    public function assertPermissionUsersInCampaign(int $campaignId, array $data): void
    {
        $ids = TokenPermissionScope::userIds($data);
        if (!$ids) return;
        $members = $this->db->table('campaign_members')->select('user_id')
            ->where('campaign_id', $campaignId)->where('is_active', 1)
            ->whereIn('user_id', $ids)->get()->getResultArray();
        $allowed = array_map('intval', array_column($members, 'user_id'));
        $campaign = $this->db->table('campaigns')->select('game_master_id')
            ->where('id', $campaignId)->get()->getRowArray();
        if (!empty($campaign['game_master_id'])) $allowed[] = (int) $campaign['game_master_id'];
        $missing = array_values(array_diff($ids, array_unique($allowed)));
        if ($missing) {
            throw new TokenException(
                'validation_failed',
                'Token permissions contain users outside this campaign.',
                422,
                ['permissions' => 'Choose active members of this campaign.']
            );
        }
    }

    private function allows(
        array $token,
        string $field,
        string $fallback,
        int $userId,
        bool $inherited
    ): bool {
        if ($userId < 1) return false;
        $scope = TokenPermissionScope::stored($token[$field] ?? null, $fallback);
        return TokenPermissionScope::allows($scope, $userId, $inherited);
    }
}
