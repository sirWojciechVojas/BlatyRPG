<?php

use App\Services\Token\TokenException;
use App\Services\Token\TokenGroupMovementPayload;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenGroupMovementPayloadTest extends CIUnitTestCase
{
    public function testNormalizesDistinctTokenMoves(): void
    {
        $moves = TokenGroupMovementPayload::validate([
            ['tokenId' => 2, 'revision' => 3, 'x' => 100, 'y' => 200],
            [
                'tokenId' => 4,
                'revision' => 5,
                'x' => 300,
                'y' => 400,
                'waypoints' => [['x' => 250, 'y' => 350]],
            ],
        ]);

        $this->assertCount(2, $moves);
        $this->assertSame(100.0, $moves[0]['x']);
        $this->assertSame([['x' => 250.0, 'y' => 350.0]], $moves[1]['waypoints']);
    }

    public function testRequiresAtLeastTwoDistinctTokens(): void
    {
        $this->expectException(TokenException::class);
        TokenGroupMovementPayload::validate([
            ['tokenId' => 2, 'revision' => 3, 'x' => 100, 'y' => 200],
            ['tokenId' => 2, 'revision' => 3, 'x' => 300, 'y' => 400],
        ]);
    }

    public function testRejectsUnexpectedClientFields(): void
    {
        $this->expectException(TokenException::class);
        TokenGroupMovementPayload::validate([
            ['tokenId' => 2, 'revision' => 3, 'x' => 100, 'y' => 200],
            [
                'tokenId' => 4,
                'revision' => 5,
                'x' => 300,
                'y' => 400,
                'movementSpent' => 0,
            ],
        ]);
    }
}
