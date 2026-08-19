<?php

use App\Services\Chat\CampaignChatAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignChatAccessPolicyTest extends CIUnitTestCase
{
    private $campaign = ['game_master_id' => 7];

    public function testPlayerCanReadAndSendByDefault(): void
    {
        $result = (new CampaignChatAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            $this->campaign,
            ['user_id' => 8, 'role' => 'player', 'is_active' => 1]
        );

        $this->assertTrue($result['canRead']);
        $this->assertTrue($result['canSend']);
        $this->assertFalse($result['canModerate']);
    }

    public function testObserverIsReadOnlyByDefault(): void
    {
        $result = (new CampaignChatAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            $this->campaign,
            ['user_id' => 8, 'role' => 'observer', 'is_active' => 1]
        );

        $this->assertTrue($result['canRead']);
        $this->assertFalse($result['canSend']);
    }

    public function testExplicitFalseStringCannotGrantCapabilities(): void
    {
        $result = (new CampaignChatAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            $this->campaign,
            [
                'user_id' => 8,
                'role' => 'assistant',
                'is_active' => 1,
                'permissions_json' => ['send_chat' => 'false', 'moderate_chat' => 'false'],
            ]
        );

        $this->assertFalse($result['canSend']);
        $this->assertFalse($result['canModerate']);
    }

    public function testSendPermissionCannotBypassReadDenial(): void
    {
        $result = (new CampaignChatAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            $this->campaign,
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 1,
                'permissions_json' => [
                    'read_chat' => false,
                    'send_chat' => true,
                    'moderate_chat' => true,
                ],
            ]
        );

        $this->assertSame(
            ['canRead' => false, 'canSend' => false, 'canModerate' => false],
            $result
        );
    }

    public function testInactiveMembershipFailsClosedDespiteOverrides(): void
    {
        $result = (new CampaignChatAccessPolicy())->evaluate(
            ['user_id' => 8, 'role' => 'user', 'anonymous' => false],
            $this->campaign,
            [
                'user_id' => 8,
                'role' => 'player',
                'is_active' => 0,
                'permissions_json' => ['read_chat' => true, 'send_chat' => true],
            ]
        );

        $this->assertSame(
            ['canRead' => false, 'canSend' => false, 'canModerate' => false],
            $result
        );
    }
}
