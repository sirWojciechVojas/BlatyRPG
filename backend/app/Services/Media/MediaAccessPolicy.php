<?php

namespace App\Services\Media;

use CodeIgniter\Database\BaseConnection;

final class MediaAccessPolicy
{
    private $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function user(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new MediaException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->db->table('users')->select('id, role')
            ->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();
        if (!$user) {
            throw new MediaException('unauthorized', 'Authentication is required.', 401);
        }
        return ['id' => $userId, 'role' => strtolower((string) ($user['role'] ?? 'user'))];
    }

    public function assertUploadAllowed(array $auth, string $visibility, ?int $campaignId): array
    {
        $user = $this->user($auth);
        if ($visibility === 'campaign') {
            if (!$campaignId || !$this->isCampaignMember($user, $campaignId)) {
                throw new MediaException('forbidden', 'Active campaign membership is required.', 403);
            }
        }
        return $user;
    }

    public function assertCanManage(array $auth, array $asset): array
    {
        $user = $this->user($auth);
        if ($user['role'] !== 'admin' && (int) ($asset['owner_user_id'] ?? 0) !== $user['id']) {
            throw new MediaException('forbidden', 'You cannot manage this media asset.', 403);
        }
        return $user;
    }

    public function assertCanView(array $auth, array $asset): void
    {
        if (($asset['visibility'] ?? '') === 'public') {
            return;
        }
        $user = $this->user($auth);
        if ($user['role'] === 'admin' || (int) ($asset['owner_user_id'] ?? 0) === $user['id']) {
            return;
        }
        if (($asset['visibility'] ?? '') === 'campaign'
            && $this->isCampaignMember($user, (int) ($asset['campaign_id'] ?? 0))) {
            return;
        }
        throw new MediaException('forbidden', 'This media asset is outside your access scope.', 403);
    }

    private function isCampaignMember(array $user, int $campaignId): bool
    {
        if ($campaignId < 1) {
            return false;
        }
        if ($user['role'] === 'admin') {
            return true;
        }
        if ($this->db->table('campaigns')->where('id', $campaignId)
            ->where('game_master_id', $user['id'])->where('deleted_at', null)->countAllResults() > 0) {
            return true;
        }
        return $this->db->table('campaign_members')->where('campaign_id', $campaignId)
            ->where('user_id', $user['id'])->where('is_active', 1)->countAllResults() > 0;
    }
}
