<?php

use App\Services\Token\TokenAccessService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenAccessServiceTest extends CIUnitTestCase
{
    public function testEvaluatesIndependentScopesAndManagerOverride(): void
    {
        $service = new TokenAccessService();
        $auth = ['user_id' => 8];
        $token = [
            'hidden' => false,
            'visible_to_json' => ['mode' => 'gm', 'userIds' => []],
            'controlled_by_json' => ['mode' => 'users', 'userIds' => [8]],
            'editable_by_json' => ['mode' => 'users', 'userIds' => [9]],
            'observer_by_json' => ['mode' => 'everyone', 'userIds' => []],
        ];

        $this->assertFalse($service->canView($auth, 4, $token, false));
        $this->assertTrue($service->canView($auth, 4, $token, true));
        $this->assertTrue($service->canControl($auth, 4, $token, false));
        $this->assertFalse($service->canEdit($auth, 4, $token, false));
        $this->assertTrue($service->canObserve($auth, 4, $token, false));
        $this->assertTrue($service->canEdit($auth, 4, $token, true));
    }

    public function testHiddenFlagOverridesPlayerVisibility(): void
    {
        $service = new TokenAccessService();
        $token = [
            'hidden' => true,
            'visible_to_json' => ['mode' => 'everyone', 'userIds' => []],
        ];

        $this->assertFalse($service->canView(['user_id' => 8], 4, $token, false));
    }
}
