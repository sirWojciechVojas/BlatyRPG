<?php

use App\Services\Character\CharacterCampaignGameGuard;
use App\Services\Character\CharacterException;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterCampaignGameGuardTest extends CIUnitTestCase
{
    public function testAcceptsTheCampaignSystemAndWorld(): void
    {
        CharacterCampaignGameGuard::assertMatches(
            ['rpg_system_id' => 1, 'rpg_universe_id' => 2],
            ['system_id' => 1, 'universe_id' => 2]
        );

        $this->assertTrue(true);
    }

    public function testRejectsClientGameThatDiffersFromCampaign(): void
    {
        try {
            CharacterCampaignGameGuard::assertMatches(
                ['rpg_system_id' => 1, 'rpg_universe_id' => 2],
                ['system_id' => 9, 'universe_id' => 8]
            );
            $this->fail('A mismatched game should be rejected.');
        } catch (CharacterException $exception) {
            $this->assertSame('campaign_game_mismatch', $exception->errorCode());
            $this->assertSame(422, $exception->status());
            $this->assertArrayHasKey('systemId', $exception->errors());
            $this->assertArrayHasKey('universeId', $exception->errors());
        }
    }
}
