<?php

use App\Services\Token\TokenPermissionScope;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenPermissionScopeTest extends CIUnitTestCase
{
    public function testSupportsGmEveryoneAndSelectedUsers(): void
    {
        $users = TokenPermissionScope::validate([
            'mode' => 'users', 'userIds' => ['8', 3, 8],
        ]);

        $this->assertTrue($users['valid']);
        $this->assertSame(['mode' => 'users', 'userIds' => [3, 8]], $users['data']);
        $this->assertTrue(TokenPermissionScope::allows($users['data'], 8, false));
        $this->assertFalse(TokenPermissionScope::allows($users['data'], 9, true));
        $this->assertTrue(TokenPermissionScope::allows(
            ['mode' => 'everyone', 'userIds' => []], 9, false
        ));
        $this->assertFalse(TokenPermissionScope::allows(
            ['mode' => 'gm', 'userIds' => []], 9, true
        ));
        $this->assertSame([3, 8], TokenPermissionScope::userIds([
            'visible_to_json' => $users['data'],
            'controlled_by_json' => ['mode' => 'users', 'userIds' => [8]],
            'name' => ['userIds' => [99]],
        ]));
    }

    public function testRejectsEmptyOrOverbroadUserScopes(): void
    {
        $empty = TokenPermissionScope::validate(['mode' => 'users', 'userIds' => []]);
        $misplaced = TokenPermissionScope::validate([
            'mode' => 'everyone', 'userIds' => [2],
        ]);

        $this->assertFalse($empty['valid']);
        $this->assertFalse($misplaced['valid']);
    }

    public function testUsesSafeFallbackForInvalidStoredData(): void
    {
        $scope = TokenPermissionScope::stored('{"mode":"bogus"}', 'gm');
        $this->assertSame(['mode' => 'gm', 'userIds' => []], $scope);
    }
}
