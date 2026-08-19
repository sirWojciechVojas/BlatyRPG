<?php

namespace App\Services\Campaign;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\ShopOwnerClaimModel;
use App\Models\UserModel;
use App\Services\Shop\ShopAuthorizationService;
use CodeIgniter\Database\BaseConnection;

class CampaignDirectoryService
{
    private $db;
    private $campaigns;
    private $members;
    private $users;
    private $policy;
    private $validator;
    private $shopAuthorization;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?CampaignAccessPolicy $policy = null,
        ?CampaignPayloadValidator $validator = null,
        ?ShopAuthorizationService $shopAuthorization = null,
        ?UserModel $users = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->campaigns = $campaigns ?: new CampaignModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->users = $users ?: new UserModel($this->db);
        $this->policy = $policy ?: new CampaignAccessPolicy();
        $this->validator = $validator ?: new CampaignPayloadValidator();
        $this->shopAuthorization = $shopAuthorization ?: new ShopAuthorizationService(
            null,
            new ShopOwnerClaimModel($this->db),
            new CampaignModel($this->db)
        );
    }

    public function listForUser(array $auth): array
    {
        $auth = $this->verifiedAuth($auth);
        $userId = (int) $auth['user_id'];
        $isAdmin = strtolower((string) $auth['role']) === 'admin';
        $query = $this->campaigns->select('campaigns.*');

        if (!$isAdmin) {
            $query->select([
                'member.user_id AS membership_user_id',
                'member.role AS membership_role',
                'member.permissions_json AS membership_permissions',
                'member.is_active AS membership_active',
            ])->join(
                'campaign_members member',
                'member.campaign_id = campaigns.id AND member.user_id = ' . $userId,
                'left'
            )->groupStart()
                ->where('campaigns.game_master_id', $userId)
                ->orWhere('member.user_id', $userId)
                ->groupEnd();
        }

        $rows = $query->orderBy('campaigns.is_active', 'DESC')
            ->orderBy('campaigns.name', 'ASC')
            ->findAll();
        $shopAccess = $this->shopAuthorization->campaignAccessMap($auth, $rows);
        $items = [];
        foreach ($rows as $row) {
            $membership = $isAdmin ? null : $this->membershipFromRow($row);
            $capabilities = $this->policy->evaluate($auth, $row, $membership);
            if (!$capabilities['canAccess']) {
                continue;
            }
            $items[] = $this->present(
                $auth,
                $row,
                $capabilities,
                $membership,
                $isAdmin,
                !empty($shopAccess[(int) $row['id']])
            );
        }

        return [
            'items' => $items,
            'capabilities' => ['canCreate' => $this->canCreate($auth)],
        ];
    }

    public function create(array $auth, array $payload): array
    {
        $auth = $this->verifiedAuth($auth);
        if (!$this->canCreate($auth)) {
            throw new CampaignException(
                'forbidden',
                'Only a game master or administrator can create campaigns.',
                403
            );
        }
        $validated = $this->validator->validateCreate($payload);
        if (!$validated['valid']) {
            throw new CampaignException(
                'validation_failed',
                'Campaign payload is invalid.',
                422,
                $validated['errors']
            );
        }

        $userId = (int) $auth['user_id'];
        $campaignData = $validated['data'];
        $campaignData['status'] = !empty($campaignData['is_active']) ? 'active' : 'paused';
        $this->db->transBegin();
        try {
            if (!$this->campaigns->insert($campaignData + [
                'game_master_id' => $userId,
                'last_activity_at' => date('Y-m-d H:i:s'),
            ])) {
                throw new CampaignException(
                    'validation_failed',
                    'Campaign could not be created.',
                    422,
                    $this->campaigns->errors()
                );
            }
            $campaignId = (int) $this->campaigns->getInsertID();
            if (!$this->members->insert([
                'campaign_id' => $campaignId,
                'user_id' => $userId,
                'role' => 'gm',
                'permissions_json' => [],
                'is_active' => 1,
            ])) {
                throw new CampaignException(
                    'campaign_write_failed',
                    'Campaign membership could not be saved.',
                    500
                );
            }
            if (!$this->db->transCommit()) {
                throw new CampaignException(
                    'campaign_write_failed',
                    'Campaign could not be saved.',
                    500
                );
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        $campaign = $this->campaigns->find($campaignId);
        $membership = [
            'user_id' => $userId,
            'role' => 'gm',
            'permissions_json' => [],
            'is_active' => 1,
        ];
        $capabilities = $this->policy->evaluate($auth, $campaign, $membership);
        $shopAccess = $this->shopAuthorization->campaignAccessMap($auth, [$campaign]);

        return ['campaign' => $this->present(
            $auth,
            $campaign,
            $capabilities,
            $membership,
            strtolower((string) $auth['role']) === 'admin',
            !empty($shopAccess[$campaignId])
        )];
    }

    private function verifiedAuth(array $auth): array
    {
        $this->assertAuthenticated($auth);
        $userId = (int) $auth['user_id'];
        $user = $this->users
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->first();
        if (!$user) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
        $auth['role'] = strtolower((string) ($user['role'] ?? 'user'));
        return $auth;
    }

    private function assertAuthenticated(array $auth): void
    {
        if ((int) ($auth['user_id'] ?? 0) < 1 || !empty($auth['anonymous'])) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
    }

    private function canCreate(array $auth): bool
    {
        return in_array(strtolower((string) ($auth['role'] ?? '')), ['gm', 'admin'], true);
    }

    private function membershipFromRow(array $row): ?array
    {
        if (empty($row['membership_user_id'])) {
            return null;
        }
        return [
            'user_id' => (int) $row['membership_user_id'],
            'role' => (string) $row['membership_role'],
            'permissions_json' => $row['membership_permissions'] ?? [],
            'is_active' => (int) $row['membership_active'],
        ];
    }

    private function present(
        array $auth,
        array $row,
        array $capabilities,
        ?array $membership,
        bool $isAdmin,
        bool $canOpenShop
    ): array {
        $isOwner = (int) $row['game_master_id'] === (int) ($auth['user_id'] ?? 0);
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'description' => $row['description'] ?? null,
            'banner_url' => $row['banner_url'] ?? null,
            'system_type' => (string) $row['system_type'],
            'is_active' => (bool) $row['is_active'],
            'status' => (string) ($row['status'] ?? (!empty($row['is_active']) ? 'active' : 'paused')),
            'settings' => is_array($row['settings_json'] ?? null) ? $row['settings_json'] : [],
            'game_master_id' => (int) $row['game_master_id'],
            'access_role' => $isAdmin
                ? 'admin'
                : ($isOwner ? 'gm' : (string) ($membership['role'] ?? 'player')),
            'capabilities' => [
                'canManage' => (bool) $capabilities['canManage'],
                'canViewHidden' => (bool) $capabilities['canViewHidden'],
                'canOpenShop' => $canOpenShop,
            ],
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
            'last_activity_at' => $row['last_activity_at'] ?? null,
        ];
    }
}
