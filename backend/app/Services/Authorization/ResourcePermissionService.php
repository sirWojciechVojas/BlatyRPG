<?php

namespace App\Services\Authorization;

use App\Models\CampaignMemberModel;
use App\Models\ResourcePermissionModel;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

/** Stores and evaluates explicit access to campaign-scoped VTT resources. */
class ResourcePermissionService
{
    private $db;
    private $permissions;
    private $members;
    private $guard;
    private $scope;
    private $access;
    private $writer;
    private $ownerAssigner;
    private $revoker;

    public function __construct(
        ?BaseConnection $db = null,
        ?ResourcePermissionModel $permissions = null,
        ?CampaignMemberModel $members = null,
        ?CampaignGuardService $guard = null,
        ?ResourceScopeService $scope = null,
        ?ResourceAccessPolicy $access = null,
        ?ResourcePermissionWriter $writer = null,
        ?CharacterOwnerAssigner $ownerAssigner = null,
        ?ResourcePermissionRevoker $revoker = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->permissions = $permissions ?: new ResourcePermissionModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->guard = $guard ?: new CampaignGuardService();
        $this->scope = $scope ?: new ResourceScopeService($this->db);
        $this->access = $access ?: new ResourceAccessPolicy();
        $this->writer = $writer ?: new ResourcePermissionWriter($this->permissions);
        $this->ownerAssigner = $ownerAssigner ?: new CharacterOwnerAssigner(
            $this->db, $this->writer
        );
        $this->revoker = $revoker ?: new ResourcePermissionRevoker(
            $this->db, $this->permissions
        );
    }

    public function levelFor(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId
    ): string {
        $type = $this->type($resourceType);
        $context = $this->guard->context($auth, $campaignId);
        $resource = $this->scope->resolve($type, $resourceId, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        $explicit = $this->permissions
            ->where('campaign_id', $campaignId)
            ->where('resource_type', $type)
            ->where('resource_id', $resourceId)
            ->where('user_id', $userId)
            ->first();

        return $this->access->levelFor(
            $context,
            $type,
            $resource,
            $explicit ? (string) $explicit['access_level'] : null
        );
    }

    public function requireLevel(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId,
        string $minimum
    ): string {
        $level = $this->levelFor($auth, $campaignId, $resourceType, $resourceId);
        if (!AccessLevel::allows($level, $minimum)) {
            throw new CampaignException(
                'forbidden',
                'Resource is outside your access scope.',
                403
            );
        }
        return $level;
    }

    public function list(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId
    ): array {
        $type = $this->type($resourceType);
        $context = $this->managementContext($auth, $campaignId, $type);
        $this->scope->resolve($type, $resourceId, $campaignId);
        $rows = $this->permissions
            ->select('resource_permissions.*, users.username, users.avatar_url')
            ->join('users', 'users.id = resource_permissions.user_id', 'inner')
            ->where('resource_permissions.campaign_id', $campaignId)
            ->where('resource_permissions.resource_type', $type)
            ->where('resource_permissions.resource_id', $resourceId)
            ->where('users.deleted_at', null)
            ->orderBy('users.username', 'ASC')
            ->findAll();
        return [
            'items' => array_map([ResourcePermissionPresenter::class, 'present'], $rows),
            'capabilities' => $context['capabilities'],
        ];
    }

    public function grant(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId,
        int $userId,
        string $level
    ): array {
        $type = $this->type($resourceType);
        $normalized = AccessLevel::normalize($level);
        if ($normalized === null) {
            throw new CampaignException(
                'validation_failed', 'Access level is invalid.', 422,
                ['accessLevel' => 'Use none, limited, observer or owner.']
            );
        }
        $context = $this->managementContext($auth, $campaignId, $type);
        $resource = $this->scope->resolve($type, $resourceId, $campaignId);
        $this->assertActiveMember($campaignId, $userId);
        if ($this->isPrimaryCharacterOwner($type, $resource, $userId)) {
            $normalized = AccessLevel::OWNER;
        }
        return $this->writer->store(
            $campaignId,
            $type,
            $resourceId,
            $userId,
            $normalized,
            (int) $context['auth']['user_id']
        );
    }

    public function revoke(
        array $auth,
        int $campaignId,
        string $resourceType,
        int $resourceId,
        int $userId
    ): array {
        $type = $this->type($resourceType);
        $this->managementContext($auth, $campaignId, $type);
        $resource = $this->scope->resolve($type, $resourceId, $campaignId);
        if ($this->isPrimaryCharacterOwner($type, $resource, $userId)) {
            throw new CampaignException(
                'primary_owner_immutable',
                'Reassign the primary owner before revoking owner access.',
                409
            );
        }
        return $this->revoker->revoke(
            $campaignId,
            $type,
            $resourceId,
            $userId
        );
    }

    public function setCharacterVisibility(
        array $auth,
        int $campaignId,
        int $characterId,
        string $level
    ): array {
        $visibility = AccessLevel::normalize($level);
        if (!in_array($visibility, [
            AccessLevel::NONE, AccessLevel::LIMITED, AccessLevel::OBSERVER,
        ], true)) {
            throw new CampaignException(
                'validation_failed', 'Character visibility is invalid.', 422,
                ['visibility' => 'Use none, limited or observer.']
            );
        }
        $actual = $this->levelFor(
            $auth,
            $campaignId,
            ResourceType::CHARACTER,
            $characterId
        );
        if (!AccessLevel::allows($actual, AccessLevel::OWNER)) {
            throw new CampaignException('forbidden', 'Owner access is required.', 403);
        }
        $updated = $this->db->table('characters')
            ->where('id', $characterId)
            ->where('campaign_id', $campaignId)
            ->update([
                'visibility_level' => $visibility,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if (!$updated) {
            throw new CampaignException(
                'character_write_failed',
                'Visibility could not be changed.',
                500
            );
        }
        return ['characterId' => $characterId, 'visibility' => $visibility];
    }

    public function assignCharacterOwner(
        array $auth,
        int $campaignId,
        int $characterId,
        int $userId,
        bool $primary
    ): array {
        $type = ResourceType::CHARACTER;
        $context = $this->managementContext($auth, $campaignId, $type);
        $this->scope->resolve($type, $characterId, $campaignId);
        $this->assertActiveMember($campaignId, $userId);
        return $this->ownerAssigner->assign(
            $campaignId,
            $characterId,
            $userId,
            $primary,
            (int) $context['auth']['user_id']
        );
    }

    private function managementContext(
        array $auth,
        int $campaignId,
        string $resourceType
    ): array {
        $context = $this->guard->context($auth, $campaignId);
        if (!$this->access->canManage($context, $resourceType)) {
            throw new CampaignException(
                'forbidden',
                'Resource manager access is required.',
                403
            );
        }
        return $context;
    }

    private function assertActiveMember(int $campaignId, int $userId): void
    {
        $member = $this->members->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->where('is_active', 1)
            ->first();
        if (!$member) {
            throw new CampaignException(
                'member_not_found',
                'Target user is not an active member.',
                422
            );
        }
    }

    private function isPrimaryCharacterOwner(
        string $type,
        array $resource,
        int $userId
    ): bool {
        return $type === ResourceType::CHARACTER
            && (int) ($resource['user_id'] ?? 0) === $userId;
    }

    private function type(string $resourceType): string
    {
        $type = ResourceType::normalize($resourceType);
        if ($type === null) {
            throw new CampaignException(
                'resource_type_invalid',
                'Resource type is invalid.',
                422
            );
        }
        return $type;
    }
}
