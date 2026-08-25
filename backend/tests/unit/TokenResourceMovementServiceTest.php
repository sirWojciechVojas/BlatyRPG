<?php

use App\Services\Token\TokenException;
use App\Services\Token\TokenMovementResource;
use App\Services\Token\TokenResourceMovementService;
use App\Services\Token\TokenResourceValidator;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenResourceMovementServiceTest extends CIUnitTestCase
{
    public function testManagerBubbleInputBecomesServerMovementState(): void
    {
        $previous = TokenMovementResource::fromMovement(
            TokenResourceValidator::stored([]),
            8,
            2
        );
        $resources = $previous;
        $resources['bubbles'][0]['linkedBarIndex'] = 1;
        $resources['bubbles'][0]['value'] = 3;
        $service = new TokenResourceMovementService(
            $this->createMock(BaseConnection::class)
        );

        $result = $service->prepare(7, $this->token($previous), [
            'bars_json' => $resources,
        ], true);

        $this->assertSame(8.0, $result['movement_range']);
        $this->assertSame(5.0, $result['movement_spent']);
        $this->assertSame(3.0, $result['bars_json']['bars'][1]['value']);
        $this->assertSame(3.0, $result['bars_json']['bubbles'][0]['value']);
    }

    public function testPlayerCannotReplenishMovementThroughResources(): void
    {
        $previous = TokenMovementResource::fromMovement(
            TokenResourceValidator::stored([]),
            8,
            2
        );
        $resources = $previous;
        $resources['bars'][1]['value'] = 8;
        $service = new TokenResourceMovementService(
            $this->createMock(BaseConnection::class)
        );

        $this->expectException(TokenException::class);
        $service->prepare(7, $this->token($previous), [
            'bars_json' => $resources,
        ], false);
    }

    private function token(array $resources): array
    {
        return [
            'campaign_id' => 7,
            'character_id' => null,
            'bars_json' => $resources,
            'movement_range' => 8,
            'movement_spent' => 2,
        ];
    }
}
