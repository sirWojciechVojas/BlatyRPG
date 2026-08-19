<?php

namespace App\Services\Scene;

use App\Services\Authorization\AccessLevel;
use App\Services\Authorization\ResourcePermissionService;
use App\Services\Authorization\ResourceType;
use App\Services\Campaign\CampaignException;

final class SceneResourceAccessService
{
    private $permissions;

    public function __construct(?ResourcePermissionService $permissions = null)
    {
        $this->permissions = $permissions ?: new ResourcePermissionService();
    }

    public function canView(
        array $auth,
        int $campaignId,
        array $scene,
        array $campaignCapabilities
    ): bool {
        if (!empty($scene['is_visible']) || !empty($campaignCapabilities['canViewHidden'])) {
            return true;
        }
        return $this->allows($auth, $campaignId, (int) $scene['id'], AccessLevel::OBSERVER);
    }

    public function canManage(
        array $auth,
        int $campaignId,
        int $sceneId,
        array $campaignCapabilities
    ): bool {
        if (!empty($campaignCapabilities['canManage'])) {
            return true;
        }
        return $this->allows($auth, $campaignId, $sceneId, AccessLevel::OWNER);
    }

    private function allows(array $auth, int $campaignId, int $sceneId, string $minimum): bool
    {
        try {
            $level = $this->permissions->levelFor(
                $auth,
                $campaignId,
                ResourceType::SCENE,
                $sceneId
            );
        } catch (CampaignException $exception) {
            if (in_array($exception->status(), [403, 404], true)) {
                return false;
            }
            throw $exception;
        }
        return AccessLevel::allows($level, $minimum);
    }
}
