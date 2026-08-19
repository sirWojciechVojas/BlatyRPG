<?php

namespace App\Controllers\Api;

use App\Services\Authorization\ResourcePermissionService;
use App\Services\Authorization\ResourceType;
use App\Services\Campaign\CampaignException;

class ResourcePermissionController extends CampaignApiController
{
    private $permissions;

    public function __construct()
    {
        parent::__construct();
        $this->permissions = new ResourcePermissionService();
    }

    public function index($campaignId = null, $resourceType = null, $resourceId = null)
    {
        return $this->execute(function () use ($campaignId, $resourceType, $resourceId): array {
            return $this->permissions->list(
                $this->auth(),
                $this->positiveId($campaignId),
                (string) $resourceType,
                $this->positiveId($resourceId, 'resource_not_found')
            );
        });
    }

    public function update(
        $campaignId = null,
        $resourceType = null,
        $resourceId = null,
        $userId = null
    ) {
        return $this->execute(function () use (
            $campaignId,
            $resourceType,
            $resourceId,
            $userId
        ): array {
            $payload = $this->jsonPayload();
            return $this->permissions->grant(
                $this->auth(),
                $this->positiveId($campaignId),
                (string) $resourceType,
                $this->positiveId($resourceId, 'resource_not_found'),
                $this->positiveId($userId, 'member_not_found'),
                (string) ($payload['accessLevel'] ?? $payload['access_level'] ?? '')
            );
        });
    }

    public function delete(
        $campaignId = null,
        $resourceType = null,
        $resourceId = null,
        $userId = null
    ) {
        return $this->execute(function () use (
            $campaignId,
            $resourceType,
            $resourceId,
            $userId
        ): array {
            return $this->permissions->revoke(
                $this->auth(),
                $this->positiveId($campaignId),
                (string) $resourceType,
                $this->positiveId($resourceId, 'resource_not_found'),
                $this->positiveId($userId, 'member_not_found')
            );
        });
    }

    public function characterVisibility($campaignId = null, $characterId = null)
    {
        return $this->execute(function () use ($campaignId, $characterId): array {
            $payload = $this->jsonPayload();
            return $this->permissions->setCharacterVisibility(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->positiveId($characterId, 'resource_not_found'),
                (string) ($payload['visibility'] ?? '')
            );
        });
    }

    public function assignCharacterOwner($campaignId = null, $characterId = null)
    {
        return $this->execute(function () use ($campaignId, $characterId): array {
            $payload = $this->jsonPayload();
            $userId = filter_var(
                $payload['userId'] ?? $payload['user_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($userId === false) {
                throw new CampaignException(
                    'validation_failed', 'A valid user id is required.', 422,
                    ['userId' => 'User id must be a positive integer.']
                );
            }
            return $this->permissions->assignCharacterOwner(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->positiveId($characterId, 'resource_not_found'),
                (int) $userId,
                filter_var($payload['primary'] ?? false, FILTER_VALIDATE_BOOLEAN)
            );
        });
    }
}
