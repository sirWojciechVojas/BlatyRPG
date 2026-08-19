<?php

use App\Services\Character\CharacterAccessPolicy;
use App\Services\Character\CharacterPresenter;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CharacterAccessLevelsTest extends CIUnitTestCase
{
    public function testObserverCanReadButCannotEditCharacter(): void
    {
        $result = (new CharacterAccessPolicy())->character(
            ['canAccess' => true, 'canManageAll' => false],
            ['campaign_id' => 12, 'user_id' => 9, 'visibility_level' => 'none'],
            7,
            12,
            'observer'
        );

        $this->assertTrue($result['canView']);
        $this->assertTrue($result['canObserve']);
        $this->assertFalse($result['canEdit']);
        $this->assertSame('observer', $result['accessLevel']);
    }

    public function testLimitedPresenterDoesNotExposeSheetOrInventoryData(): void
    {
        $result = CharacterPresenter::present([
            'id' => 5,
            'campaign_id' => 12,
            'user_id' => 9,
            'name' => 'Known stranger',
            'data' => ['secret' => 'hidden'],
            'assets' => ['token' => ['url' => '/private-token.webp']],
            'brass' => 200,
            '_permissions' => [
                'canView' => true,
                'canObserve' => false,
                'canEdit' => false,
                'accessLevel' => 'limited',
            ],
        ]);

        $this->assertSame([], $result['data']);
        $this->assertSame([], $result['assets']);
        $this->assertSame(0, $result['brass']);
        $this->assertSame('limited', $result['accessLevel']);
    }

    public function testCharacterIdFromAnotherCampaignAlwaysFailsClosed(): void
    {
        $result = (new CharacterAccessPolicy())->character(
            ['canAccess' => true, 'canManageAll' => true, 'isAdmin' => true],
            ['campaign_id' => 99, 'user_id' => 7],
            7,
            12,
            'owner'
        );

        $this->assertFalse($result['canView']);
        $this->assertFalse($result['canEdit']);
    }
}
