<?php

use App\Services\Token\TokenException;
use App\Services\Token\TokenMovementRoute;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenMovementRouteTest extends CIUnitTestCase
{
    public function testNormalizesValidWaypoints(): void
    {
        $this->assertSame(
            [['x' => 10.5, 'y' => -2.0]],
            TokenMovementRoute::validate([['x' => '10.5', 'y' => -2]])
        );
    }

    public function testRejectsMalformedWaypoint(): void
    {
        $this->expectException(TokenException::class);
        TokenMovementRoute::validate([['x' => 10]]);
    }

    public function testRejectsMoreThanTwentyWaypoints(): void
    {
        $this->expectException(TokenException::class);
        TokenMovementRoute::validate(array_fill(0, 21, ['x' => 1, 'y' => 2]));
    }
}
