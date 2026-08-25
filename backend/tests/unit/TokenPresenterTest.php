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
            'elevation' => 0,
            'disposition' => 'friendly',
            'hidden' => 0,
            'locked' => 1,
            'revision' => 3,
        ], true, false);

        $this->assertTrue($token['locked']);
        $this->assertTrue($token['capabilities']['canControl']);
    }
}
