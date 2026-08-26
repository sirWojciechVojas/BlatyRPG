<?php

use App\Services\Combat\CombatTurnSequence;
use App\Services\Combat\CombatMovementValue;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CombatTurnSequenceTest extends CIUnitTestCase
{
    public function testAdvancesTurnAndStartsANewRoundOnWrap(): void
    {
        $this->assertSame(
            ['round' => 2, 'turnIndex' => 0, 'newRound' => true],
            CombatTurnSequence::advance(1, 2, 3, 1)
        );
    }

    public function testMovesBackWithoutCreatingRoundZero(): void
    {
        $this->assertSame(
            ['round' => 1, 'turnIndex' => 2, 'newRound' => false],
            CombatTurnSequence::advance(1, 0, 3, -1)
        );
    }

    public function testRejectsManipulatedMovementValues(): void
    {
        $this->expectException(\App\Services\Combat\CombatException::class);
        CombatMovementValue::parse('not-a-number');
    }
}
