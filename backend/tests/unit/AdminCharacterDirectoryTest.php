<?php

use App\Services\Admin\AdminCharacterDirectory;
use PHPUnit\Framework\TestCase;

final class AdminCharacterDirectoryTest extends TestCase
{
    public function testBuildsCharactersAndCampaignScopedGameMasters(): void
    {
        $result = AdminCharacterDirectory::assemble(
            [[
                'id' => '11',
                'name' => 'Bruder Witz',
                'campaign_id' => '5',
                'campaign_name' => 'Tysiąc Tronów',
                'owner_id' => '8',
                'owner_name' => 'gracz',
                'updated_at' => '2026-08-25 10:00:00',
            ]],
            [
                [
                    'campaign_id' => '5', 'user_id' => '2',
                    'username' => 'admin', 'game_master_id' => '2',
                ],
                [
                    'campaign_id' => '5', 'user_id' => '7',
                    'username' => 'drugi_mg', 'game_master_id' => '2',
                ],
            ]
        );

        $this->assertSame(11, $result['characters'][0]['id']);
        $this->assertSame(5, $result['characters'][0]['campaignId']);
        $this->assertSame('gracz', $result['characters'][0]['ownerName']);
        $this->assertSame(2, $result['characterGameMasters'][0]['userId']);
        $this->assertTrue($result['characterGameMasters'][0]['isCampaignOwner']);
        $this->assertFalse($result['characterGameMasters'][1]['isCampaignOwner']);
    }

    public function testRejectsInvalidRowsAndDeduplicatesCandidates(): void
    {
        $result = AdminCharacterDirectory::assemble(
            [['id' => 0], ['id' => 3, 'name' => 'NPC']],
            [
                ['campaign_id' => 1, 'user_id' => 4, 'username' => 'MG'],
                ['campaign_id' => 1, 'user_id' => 4, 'username' => 'MG'],
                ['campaign_id' => 0, 'user_id' => 4, 'username' => 'Błędny'],
            ]
        );

        $this->assertCount(1, $result['characters']);
        $this->assertNull($result['characters'][0]['campaignId']);
        $this->assertCount(1, $result['characterGameMasters']);
    }
}
