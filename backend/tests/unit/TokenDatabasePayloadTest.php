<?php

use App\Services\Token\TokenDatabasePayload;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenDatabasePayloadTest extends CIUnitTestCase
{
    public function testEncodesOnlyTokenJsonColumns(): void
    {
        $result = TokenDatabasePayload::encode([
            'name' => 'Guard',
            'bars_json' => ['bars' => [['value' => 7]]],
            'statuses_json' => ['poisoned'],
        ]);

        $this->assertSame('Guard', $result['name']);
        $this->assertSame(['bars' => [['value' => 7]]], json_decode($result['bars_json'], true));
        $this->assertSame(['poisoned'], json_decode($result['statuses_json'], true));
    }
}
