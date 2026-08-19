<?php

namespace App\Services\Authorization;

use App\Models\ResourcePermissionModel;
use App\Services\Campaign\CampaignException;

/** Persists one explicit grant without applying unrelated domain side effects. */
class ResourcePermissionWriter
{
    private $permissions;

    public function __construct(ResourcePermissionModel $permissions)
    {
        $this->permissions = $permissions;
    }

    public function store(
        int $campaignId,
        string $type,
        int $resourceId,
        int $userId,
        string $level,
        int $grantorId
    ): array {
        $key = [
            'campaign_id' => $campaignId,
            'resource_type' => $type,
            'resource_id' => $resourceId,
            'user_id' => $userId,
        ];
        $data = [
            'access_level' => $level,
            'granted_by_user_id' => $grantorId,
        ];
        $existing = $this->permissions->where($key)->first();
        $ok = $existing
            ? $this->permissions->update((int) $existing['id'], $data)
            : $this->permissions->insert($key + $data);
        if (!$ok) {
            throw new CampaignException(
                'permission_write_failed',
                'Permission could not be saved.',
                500
            );
        }
        $row = $this->permissions->where($key)->first();
        return ['permission' => ResourcePermissionPresenter::present($row)];
    }
}
