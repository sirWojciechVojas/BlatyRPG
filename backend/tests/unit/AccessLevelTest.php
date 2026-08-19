<?php

use App\Services\Authorization\AccessLevel;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class AccessLevelTest extends CIUnitTestCase
{
    public function testLevelsAreOrderedFromNoneToOwner(): void
    {
        $this->assertTrue(AccessLevel::allows('owner', 'observer'));
        $this->assertTrue(AccessLevel::allows('observer', 'limited'));
        $this->assertTrue(AccessLevel::allows('limited', 'limited'));
        $this->assertFalse(AccessLevel::allows('limited', 'observer'));
        $this->assertFalse(AccessLevel::allows('none', 'limited'));
    }

    public function testUnknownLevelFailsClosed(): void
    {
        $this->assertNull(AccessLevel::normalize('administrator'));
        $this->assertFalse(AccessLevel::allows('administrator', 'none'));
    }
}
