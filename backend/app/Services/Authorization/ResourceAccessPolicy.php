<?php

namespace App\Services\Authorization;

/** Pure resource-level authorization policy. */
class ResourceAccessPolicy
{
    public function canManage(array $context, string $resourceType): bool
    {
        if (!empty($context['isAdmin']) || !empty($context['isOwner'])
            || !empty($context['capabilities']['canManage'])) {
            return true;
        }

        $capability = $this->managementCapability($resourceType);
        return $capability !== null
            && !empty($context['capabilities'][$capability]);
    }

    public function levelFor(
        array $context,
        string $resourceType,
        array $resource,
        ?string $explicitLevel
    ): string {
        if ($this->canManage($context, $resourceType)) {
            return AccessLevel::OWNER;
        }

        $userId = (int) ($context['auth']['user_id'] ?? 0);
        if ($resourceType === ResourceType::CHARACTER
            && (int) ($resource['user_id'] ?? 0) === $userId) {
            return AccessLevel::OWNER;
        }

        if ($explicitLevel !== null) {
            return AccessLevel::normalize($explicitLevel) ?: AccessLevel::NONE;
        }

        return $this->defaultLevel($context, $resourceType, $resource);
    }

    private function managementCapability(string $resourceType): ?string
    {
        $map = [
            ResourceType::CAMPAIGN => 'canManage',
            ResourceType::CHARACTER => 'canManageCharacters',
            ResourceType::SCENE => 'canManageScenes',
            ResourceType::JOURNAL => 'canManage',
            ResourceType::ITEM => 'canManage',
            ResourceType::SHARED => 'canManage',
        ];
        return $map[$resourceType] ?? null;
    }

    private function defaultLevel(
        array $context,
        string $resourceType,
        array $resource
    ): string {
        if ($resourceType === ResourceType::CAMPAIGN) {
            return AccessLevel::normalize(
                $context['capabilities']['accessLevel'] ?? null
            ) ?: AccessLevel::LIMITED;
        }
        if ($resourceType === ResourceType::SCENE && !empty($resource['is_visible'])) {
            return AccessLevel::OBSERVER;
        }
        if ($resourceType === ResourceType::CHARACTER) {
            return AccessLevel::normalize($resource['visibility_level'] ?? null)
                ?: AccessLevel::NONE;
        }
        return AccessLevel::NONE;
    }
}
