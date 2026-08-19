<?php

use App\Services\Character\CharacterPresenter;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterPresenterTest extends CIUnitTestCase
{
    public function testPresenterExposesSafeCanonicalCharacterShape(): void
    {
        $result = CharacterPresenter::present([
            'id' => '4',
            'campaign_id' => '2',
            'user_id' => '8',
            'system_id' => '1',
            'universe_id' => '3',
            'name' => 'Roch',
            'data' => ['details' => ['race' => 'human']],
            'password_hash' => 'must-not-leak',
            'brass' => -2,
            'primary_currency_code' => 'wfrp_empire',
            '_permissions' => ['canEdit' => true, 'canDelete' => false],
        ]);

        $this->assertSame(4, $result['id']);
        $this->assertSame(0, $result['brass']);
        $this->assertTrue($result['capabilities']['canEdit']);
        $this->assertArrayNotHasKey('password_hash', $result);
    }
}
