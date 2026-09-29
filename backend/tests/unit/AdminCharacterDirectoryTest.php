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
                'updated_at' => '2026-08-25 10:00:00',
            ]],
            [
                ['character_id' => 11, 'campaign_id' => 5, 'campaign_name' => 'Tysiąc Tronów'],
                ['character_id' => 11, 'campaign_id' => 8, 'campaign_name' => 'Drugi Stół'],
            ],
            [
                [
                    'character_id' => 11, 'campaign_id' => 5,
                    'user_id' => 2, 'username' => 'admin',
                ],
                [
                    'character_id' => 11, 'campaign_id' => 8,
                    'user_id' => 7, 'username' => 'drugi_mg',
                ],
            ],
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
        $this->assertCount(2, $result['characterCampaigns']);
        $this->assertCount(2, $result['characterOwners']);
        $this->assertSame(2, $result['characterGameMasters'][0]['userId']);
        $this->assertTrue($result['characterGameMasters'][0]['isCampaignOwner']);
        $this->assertFalse($result['characterGameMasters'][1]['isCampaignOwner']);
    }

    public function testRejectsInvalidRowsAndDeduplicatesCandidates(): void
    {
        $result = AdminCharacterDirectory::assemble(
            [['id' => 0], ['id' => 3, 'name' => 'NPC']],
            [['character_id' => 0, 'campaign_id' => 1]],
            [],
            [
                ['campaign_id' => 1, 'user_id' => 4, 'username' => 'MG'],
                ['campaign_id' => 1, 'user_id' => 4, 'username' => 'MG'],
                ['campaign_id' => 0, 'user_id' => 4, 'username' => 'Błędny'],
            ]
        );

        $this->assertCount(1, $result['characters']);
        $this->assertCount(0, $result['characterCampaigns']);
        $this->assertCount(1, $result['characterGameMasters']);
    }
}
