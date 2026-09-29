<?php

namespace Tests\Unit;

use App\Services\Journal\HeroJournalAccessPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class HeroJournalAccessPolicyTest extends CIUnitTestCase
{
    public function testPrivateEntryIsVisibleOnlyToItsOwner(): void
    {
        $policy = new HeroJournalAccessPolicy();
        $entry = ['owner_user_id' => 7, 'visibility' => 'private'];

        $this->assertTrue($policy->canView($entry, $this->context(7, false, 'player')));
        $this->assertFalse($policy->canView($entry, $this->context(9, true, 'gm')));
        $this->assertFalse($policy->canEdit($entry, $this->context(9, true, 'gm')));
    }

    public function testGmAndPlayerVisibilityIncludesOwnerAndManager(): void
    {
        $policy = new HeroJournalAccessPolicy();
        $entry = ['owner_user_id' => 7, 'visibility' => 'gm_player'];

        $this->assertTrue($policy->canView($entry, $this->context(7, false, 'player')));
        $this->assertTrue($policy->canView($entry, $this->context(9, true, 'gm')));
        $this->assertTrue($policy->canEdit($entry, $this->context(9, true, 'gm')));
        $this->assertFalse($policy->canView($entry, $this->context(11, false, 'player')));
    }

    public function testCampaignAndPublicCampaignVisibilityDifferForObserver(): void
    {
        $policy = new HeroJournalAccessPolicy();
        $context = $this->context(11, false, 'observer');

        $this->assertFalse($policy->canView(
            ['owner_user_id' => 7, 'visibility' => 'campaign'],
            $context
        ));
        $this->assertTrue($policy->canView(
            ['owner_user_id' => 7, 'visibility' => 'public_campaign'],
            $context
        ));
    }

    public function testSensitiveSectionOverrideRemainsPrivate(): void
    {
        $policy = new HeroJournalAccessPolicy();
        $entry = ['owner_user_id' => 7, 'visibility' => 'campaign'];

        $this->assertFalse($policy->canView(
            $entry,
            $this->context(9, true, 'gm'),
            'private'
        ));
    }

    private function context(int $userId, bool $manager, string $role): array
    {
        return ['userId' => $userId, 'isManager' => $manager, 'membershipRole' => $role];
    }
}
