<?php

use App\Services\Token\TokenPresenter;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenPresenterTest extends CIUnitTestCase
{
    public function testKeepsControlAndLockAsIndependentFlags(): void
    {
        $token = TokenPresenter::present([
            'id' => 9,
            'scene_id' => 4,
            'character_id' => 12,
            'name' => 'Guard',
            'image_url' => null,
            'x' => 10,
            'y' => 20,
            'width' => 100,
            'height' => 100,
            'rotation' => 90,
            'facing' => 90,
            'rotation_handle_enabled' => 1,
            'facing_handle_enabled' => 0,
            'rotation_follows_facing' => 1,
            'movement_range' => 8,
            'movement_spent' => 2.5,
            'movement_reset_mode' => 'round',
            'show_info_unselected' => 1,
            'resource_bar_position' => 'above',
            'elevation' => 0,
            'disposition' => 'friendly',
            'hidden' => 0,
            'locked' => 1,
            'visible_to_json' => ['mode' => 'users', 'userIds' => [8]],
            'controlled_by_json' => ['mode' => 'users', 'userIds' => [8]],
            'editable_by_json' => ['mode' => 'gm', 'userIds' => []],
            'observer_by_json' => ['mode' => 'everyone', 'userIds' => []],
            'bars_json' => ['bubbles' => [[
                'enabled' => true, 'label' => 'KP', 'value' => 2,
                'position' => 'top-left',
            ]]],
            'revision' => 3,
        ], true, false, false, true);

        $this->assertTrue($token['locked']);
        $this->assertTrue($token['rotationHandleEnabled']);
        $this->assertFalse($token['facingHandleEnabled']);
        $this->assertTrue($token['rotationFollowsFacing']);
        $this->assertSame(8.0, $token['movementRange']);
        $this->assertSame(2.5, $token['movementSpent']);
        $this->assertSame(5.5, $token['movementPoints']);
        $this->assertSame('round', $token['movementResetMode']);
        $this->assertTrue($token['showInfoUnselected']);
        $this->assertSame('above', $token['resourceBarPosition']);
        $this->assertTrue($token['capabilities']['canControl']);
        $this->assertTrue($token['capabilities']['canObserve']);
        $this->assertSame(['mode' => 'users', 'userIds' => [8]], $token['visibleTo']);
        $this->assertCount(4, $token['resources']['bars']);
        $this->assertSame('HP', $token['resources']['bars'][0]['label']);
        $this->assertSame('#d95d55', $token['resources']['bars'][0]['color']);
        $this->assertSame('PR', $token['resources']['bars'][1]['label']);
        $this->assertSame('#4caf72', $token['resources']['bars'][1]['color']);
        $this->assertTrue($token['resources']['bars'][1]['movementSource']);
        $this->assertSame(5.5, $token['resources']['bars'][1]['value']);
        $this->assertSame(8.0, $token['resources']['bars'][1]['max']);
        $this->assertSame(2.0, $token['resources']['bubbles'][0]['value']);
    }
}
