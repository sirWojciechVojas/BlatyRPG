<?php

use App\Services\Token\TokenException;
use App\Services\Token\TokenMovementBudget;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenMovementBudgetTest extends CIUnitTestCase
{
    public function testAllowsRequestsWhileMovementPointsRemain(): void
    {
        $token = ['movement_range' => 6, 'movement_spent' => 5];

        $this->assertSame(1.0, TokenMovementBudget::remaining($token));
        TokenMovementBudget::assertAvailable($token);
        $this->addToAssertionCount(1);
    }

    public function testRejectsRequestsAfterMovementPointsAreDepleted(): void
    {
        $token = ['movement_range' => 6, 'movement_spent' => 34];

        $this->assertSame(0.0, TokenMovementBudget::remaining($token));
        try {
            TokenMovementBudget::assertAvailable($token);
            $this->fail('A depleted token was allowed to request movement.');
        } catch (TokenException $exception) {
            $this->assertSame('movement_points_depleted', $exception->errorCode());
            $this->assertSame(422, $exception->status());
        }
    }
}
