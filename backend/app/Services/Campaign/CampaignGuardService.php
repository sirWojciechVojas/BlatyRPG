<?php

namespace App\Services\Campaign;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\UserModel;

/** Canonical database-backed campaign authorization context. */
class CampaignGuardService
{
    private $campaigns;
    private $members;
    private $users;
    private $policy;

    public function __construct(
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?UserModel $users = null,
        ?CampaignAccessPolicy $policy = null
    ) {
        $this->campaigns = $campaigns ?: new CampaignModel();
        $this->members = $members ?: new CampaignMemberModel();
        $this->users = $users ?: new UserModel();
        $this->policy = $policy ?: new CampaignAccessPolicy();
    }

    public function context(array $auth, int $campaignId): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->users
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->first();
        if (!$user) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
        $auth['role'] = $this->globalRole($user['role'] ?? null);
        $campaign = $campaignId > 0 ? $this->campaigns->find($campaignId) : null;
        if (!$campaign) {
            throw new CampaignException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $membership = $this->members
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->first();
        $capabilities = $this->policy->evaluate($auth, $campaign, $membership);
        if (!$capabilities['canAccess']) {
            throw new CampaignException('forbidden', 'Campaign is outside your access scope.', 403);
        }
        $isOwner = (int) ($campaign['game_master_id'] ?? 0) === $userId;
        $isAdmin = $auth['role'] === 'admin';

        return [
            'auth' => $auth,
            'user' => $user,
            'campaign' => $campaign,
            'membership' => $membership,
            'capabilities' => [
                'canManage' => !empty($capabilities['canManage']),
                'canManageScenes' => !empty($capabilities['canManageScenes']),
                'canManageCharacters' => !empty($capabilities['canManageCharacters']),
                'canViewHidden' => !empty($capabilities['canViewHidden']),
                'accessLevel' => (string) ($capabilities['accessLevel'] ?? 'none'),
            ],
            'accessRole' => $isAdmin
                ? 'admin'
                : ($isOwner ? CampaignRole::GM : (string) ($membership['role'] ?? 'player')),
            'isOwner' => $isOwner,
            'isAdmin' => $isAdmin,
        ];
    }

    public function requireManage(array $auth, int $campaignId): array
    {
        $context = $this->context($auth, $campaignId);
        if (!$context['capabilities']['canManage']) {
            throw new CampaignException('forbidden', 'Campaign manager access is required.', 403);
        }
        return $context;
    }

    public function touchActivity(int $campaignId): void
    {
        $this->campaigns->update($campaignId, ['last_activity_at' => date('Y-m-d H:i:s')]);
    }

    private function globalRole($role): string
    {
        $normalized = strtolower(trim((string) $role));
        return $normalized === 'user' ? 'player' : $normalized;
    }
}
