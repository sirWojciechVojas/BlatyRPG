<?php

use App\Services\Campaign\CampaignSettingsValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignSettingsValidatorTest extends CIUnitTestCase
{
    public function testNormalizesStatusAndSettings(): void
    {
        $result = (new CampaignSettingsValidator())->validate([
            'status' => 'paused',
            'bannerUrl' => '/uploads/campaigns/banner.webp',
            'settings' => ['diceVisibility' => 'public'],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(0, $result['data']['is_active']);
        $this->assertSame('paused', $result['data']['status']);
    }

    public function testRejectsJavascriptBannerAndUnknownStatus(): void
    {
        $result = (new CampaignSettingsValidator())->validate([
            'status' => 'root',
            'bannerUrl' => 'javascript:alert(1)',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('status', $result['errors']);
        $this->assertArrayHasKey('bannerUrl', $result['errors']);
    }

    public function testAcceptsManagedSystemAndWorldPair(): void
    {
        $result = (new CampaignSettingsValidator())->validate([
            'systemId' => 4,
            'universeId' => 9,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(4, $result['data']['rpg_system_id']);
        $this->assertSame(9, $result['data']['rpg_universe_id']);
    }

    public function testValidatesStructuredTableSettings(): void
    {
        $valid = (new CampaignSettingsValidator())->validate([
            'settings' => [
                'tableVisibility' => 'invite_only',
                'diceVisibility' => 'gm',
                'allowPlayerDrawing' => false,
                'defaultGridSize' => 64,
            ],
        ]);
        $invalid = (new CampaignSettingsValidator())->validate([
            'settings' => ['defaultGridSize' => 500, 'isAdmin' => true],
        ]);

        $this->assertTrue($valid['valid']);
        $this->assertSame(64, $valid['data']['settings_json']['defaultGridSize']);
        $this->assertFalse($invalid['valid']);
        $this->assertArrayHasKey('settings', $invalid['errors']);
        $this->assertArrayHasKey('settings.defaultGridSize', $invalid['errors']);
    }

    /** @dataProvider unsafeLocalBannerProvider */
    public function testRejectsTraversalAndBackslashInLocalBanner(string $banner): void
    {
        $result = (new CampaignSettingsValidator())->validate(['bannerUrl' => $banner]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('bannerUrl', $result['errors']);
    }

    public function unsafeLocalBannerProvider(): array
    {
        return [['/assets/../secret'], ['/assets\\secret'], ['//evil.example/banner']];
    }
}
