<?php

namespace App\Services\Authorization;

use App\Models\ResourcePermissionModel;
use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;

/** Atomically revokes a grant and its legacy character-owner projection. */
class ResourcePermissionRevoker
{
    private $db;
    private $permissions;

    public function __construct(
        BaseConnection $db,
        ResourcePermissionModel $permissions
    ) {
        $this->db = $db;
        $this->permissions = $permissions;
    }

    public function revoke(
        int $campaignId,
        string $type,
        int $resourceId,
        int $userId
    ): array {
        $key = [
            'campaign_id' => $campaignId,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'user_id' => $userId,
        ];
        $row = $this->permissions->where($key)->first();
        if (!$row) {
            throw new CampaignException(
                'permission_not_found',
                'Permission was not found.',
                404
            );
        }

        $this->db->transBegin();
        try {
            if (!$this->permissions->delete((int) $row['id'])) {
                throw $this->writeFailure();
            }
            $this->deleteLegacyCharacterClaim(
                $campaignId,
                $type,
                $resourceId,
                $userId
            );
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw $this->writeFailure();
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return ['revoked' => true, 'userId' => $userId];
    }

    private function deleteLegacyCharacterClaim(
        int $campaignId,
        string $type,
        int $resourceId,
        int $userId
    ): void {
        if ($type !== ResourceType::CHARACTER
            || !$this->db->tableExists('shop_owner_claims')) {
            return;
        }
        $deleted = $this->db->table('shop_owner_claims')
            ->where('campaign_id', $campaignId)
            ->where('character_id', $resourceId)
            ->where('user_id', $userId)
            ->delete();
        if ($deleted === false) {
            throw $this->writeFailure();
        }
    }

    private function writeFailure(): CampaignException
    {
        return new CampaignException(
            'permission_write_failed',
            'Permission could not be revoked.',
            500
        );
    }
}
