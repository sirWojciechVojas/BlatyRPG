<?php

use App\Services\Character\CharacterException;
use App\Services\Character\CharacterRevisionPolicy;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterRevisionPolicyTest extends CIUnitTestCase
{
    private $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new CharacterRevisionPolicy();
    }

    public function testRevisionAndLegacyTimestampCanMatch(): void
    {
        $row = ['revision' => '4', 'updated_at' => '2026-08-19 12:00:00'];

        $this->assertSame(4, $this->policy->assertExpected($row, 4, null));
        $this->assertSame(
            4,
            $this->policy->assertExpected($row, null, '2026-08-19T12:00:00Z')
        );
    }

    public function testStaleRevisionReturnsConflictWithCurrentRevision(): void
    {
        try {
            $this->policy->assertExpected(['revision' => 5], 4, null);
            $this->fail('A stale revision must fail.');
        } catch (CharacterException $exception) {
            $this->assertSame(409, $exception->status());
            $this->assertSame('character_conflict', $exception->errorCode());
            $this->assertSame(5, $exception->errors()['currentRevision']);
        }
    }

    public function testNextTimestampIsMonotonicForLegacyClients(): void
    {
        $current = '2099-01-01 00:00:00';
        $next = $this->policy->nextTimestamp(['updated_at' => $current]);

        $this->assertSame(strtotime($current) + 1, strtotime($next));
    }
}
