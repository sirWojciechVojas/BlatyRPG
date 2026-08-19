<?php

use App\Services\Authorization\AccessLevel;
use App\Services\Authorization\ResourceAccessPolicy;
use App\Services\Authorization\ResourceType;
use App\Services\Campaign\CampaignAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ResourceAccessPolicyTest extends CIUnitTestCase
{
    public function testSceneAndCharacterManagementAreIsolated(): void
    {
        $policy = new ResourceAccessPolicy();
        $sceneManager = $this->context([
            'canManageScenes' => true,
            'canManageCharacters' => false,
        ]);
        $characterManager = $this->context([
            'canManageScenes' => false,
            'canManageCharacters' => true,
        ]);

        $this->assertTrue($policy->canManage($sceneManager, ResourceType::SCENE));
        $this->assertFalse($policy->canManage($sceneManager, ResourceType::CHARACTER));
        $this->assertFalse($policy->canManage($sceneManager, ResourceType::CAMPAIGN));
        $this->assertTrue($policy->canManage($characterManager, ResourceType::CHARACTER));
        $this->assertFalse($policy->canManage($characterManager, ResourceType::SCENE));
        $this->assertFalse($policy->canManage($characterManager, ResourceType::CAMPAIGN));
    }

    public function testCampaignManagerCanManageEveryCurrentResourceDomain(): void
    {
        $policy = new ResourceAccessPolicy();
        $context = $this->context(['canManage' => true]);

        $this->assertTrue($policy->canManage($context, ResourceType::CAMPAIGN));
        $this->assertTrue($policy->canManage($context, ResourceType::CHARACTER));
        $this->assertTrue($policy->canManage($context, ResourceType::SCENE));
        $this->assertTrue($policy->canManage($context, ResourceType::ITEM));
    }

    public function testPrimaryCharacterOwnerCannotBeDowngradedByExplicitNone(): void
    {
        $level = (new ResourceAccessPolicy())->levelFor(
            $this->context([]),
            ResourceType::CHARACTER,
            ['id' => 5, 'user_id' => 7, 'visibility_level' => AccessLevel::NONE],
            AccessLevel::NONE
        );

        $this->assertSame(AccessLevel::OWNER, $level);
    }

    public function testInactiveMembershipIgnoresStoredCapabilities(): void
    {
        $result = (new CampaignAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'player', 'anonymous' => false],
            ['game_master_id' => 7],
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 0,
                'permissions_json' => [
                    'manage_campaign' => true,
                    'manage_scenes' => true,
                    'manage_characters' => true,
                ],
            ]
        );

        $this->assertFalse($result['canAccess']);
        $this->assertFalse($result['canManage']);
        $this->assertFalse($result['canManageScenes']);
        $this->assertFalse($result['canManageCharacters']);
    }

    private function context(array $capabilities): array
    {
        return [
            'auth' => ['user_id' => 7],
            'isAdmin' => false,
            'isOwner' => false,
            'capabilities' => $capabilities + [
                'canManage' => false,
                'canManageScenes' => false,
                'canManageCharacters' => false,
                'accessLevel' => AccessLevel::LIMITED,
            ],
        ];
    }
}
