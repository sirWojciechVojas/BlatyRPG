<?php

namespace App\Services\Campaign;

use App\Models\CampaignMemberModel;
use CodeIgniter\Database\BaseConnection;

class CampaignMemberService
{
    private $members;
    private $guard;
    private $db;

    public function __construct(
        ?CampaignMemberModel $members = null,
        ?CampaignGuardService $guard = null,
        ?BaseConnection $db = null
    ) {
        $this->members = $members ?: new CampaignMemberModel();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->db = $db ?: \Config\Database::connect();
    }

    public function list(array $auth, int $campaignId): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $canManage = $context['capabilities']['canManage'];
        $rows = $this->members
            ->select('campaign_members.*, users.username, users.email, users.avatar_url')
            ->join('users', 'users.id = campaign_members.user_id', 'inner')
            ->where('campaign_members.campaign_id', $campaignId)
            ->where('campaign_members.is_active', 1)
            ->where('users.deleted_at', null)
            ->orderBy('campaign_members.role', 'ASC')
            ->orderBy('users.username', 'ASC')
            ->findAll();
        return [
            'items' => array_map(static function (array $row) use ($canManage): array {
                return CampaignMemberPresenter::present($row, $canManage);
            }, $rows),
            'capabilities' => $context['capabilities'],
        ];
    }

    public function changeRole(array $auth, int $campaignId, int $userId, array $payload): array
    {
        $context = $this->guard->requireManage($auth, $campaignId);
        $this->assertNotOwner($context, $userId);
        $role = CampaignRole::normalize($payload['role'] ?? null);
        if ($role === null) {
            throw new CampaignException(
                'validation_failed',
                'Campaign role is invalid.',
                422,
                ['role' => 'Use gm, assistant, player or observer.']
            );
        }
        $member = $this->activeMember($campaignId, $userId);
        if (!$this->members->update((int) $member['id'], ['role' => $role])) {
            throw new CampaignException('membership_write_failed', 'Role could not be changed.', 500);
        }
        $member['role'] = $role;
        return ['member' => CampaignMemberPresenter::present($member, true)];
    }

    public function remove(array $auth, int $campaignId, int $userId): array
    {
        $context = $this->guard->requireManage($auth, $campaignId);
        $this->assertNotOwner($context, $userId);
        $member = $this->activeMember($campaignId, $userId);
        $this->db->transBegin();
        try {
            if (!$this->members->update((int) $member['id'], [
                'is_active' => 0,
                'permissions_json' => [],
                'left_at' => date('Y-m-d H:i:s'),
            ])) {
                throw new CampaignException('membership_write_failed', 'Member could not be removed.', 500);
            }
            if ($this->db->tableExists('resource_permissions')) {
                $this->db->table('resource_permissions')->where('campaign_id', $campaignId)
                    ->where('user_id', $userId)->delete();
            }
            if ($this->db->tableExists('shop_owner_claims')) {
                $this->db->table('shop_owner_claims')->where('campaign_id', $campaignId)
                    ->where('user_id', $userId)->delete();
            }
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new CampaignException('membership_write_failed', 'Member could not be removed.', 500);
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['removed' => true, 'userId' => $userId];
    }

    public function activate(int $campaignId, int $userId, string $role): array
    {
        $member = $this->members
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->first();
        $data = [
            'role' => $role,
            'is_active' => 1,
            'permissions_json' => [],
            'joined_at' => date('Y-m-d H:i:s'),
            'left_at' => null,
        ];
        if ($member) {
            if (!$this->members->update((int) $member['id'], $data)) {
                throw new CampaignException('membership_write_failed', 'Membership could not be activated.', 500);
            }
            return $data + $member;
        }
        $data += [
            'campaign_id' => $campaignId,
            'user_id' => $userId,
        ];
        if (!$this->members->insert($data)) {
            throw new CampaignException('membership_write_failed', 'Membership could not be created.', 500);
        }
        return ['id' => (int) $this->members->getInsertID()] + $data;
    }

    private function activeMember(int $campaignId, int $userId): array
    {
        $member = $this->members
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->first();
        if (!$member) {
            throw new CampaignException('member_not_found', 'Campaign member was not found.', 404);
        }
        return $member;
    }

    private function assertNotOwner(array $context, int $userId): void
    {
        if ((int) $context['campaign']['game_master_id'] === $userId) {
            throw new CampaignException('owner_immutable', 'Campaign owner cannot be removed or reassigned.', 409);
        }
    }
}
