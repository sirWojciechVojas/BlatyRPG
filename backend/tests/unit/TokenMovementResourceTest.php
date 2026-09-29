<?php

use App\Services\Token\TokenMovementResource;
use App\Services\Token\TokenResourceValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenMovementResourceTest extends CIUnitTestCase
{
    public function testLinkedBubbleSetsTheMovementBarAndAvailablePoints(): void
    {
        $previous = TokenResourceValidator::stored([]);
        $resources = $previous;
        $resources['bubbles'][0] = array_merge($resources['bubbles'][0], [
            'enabled' => true,
            'value' => 4,
            'linkedBarIndex' => 1,
        ]);

        $input = TokenMovementResource::applyBubbleInputs($previous, $resources);
        $movement = TokenMovementResource::fromResources($input, 6, 0);

        $this->assertSame(4.0, $movement['resources']['bars'][1]['value']);
        $this->assertSame(4.0, $movement['resources']['bubbles'][0]['value']);
        $this->assertSame(6.0, $movement['range']);
        $this->assertSame(2.0, $movement['spent']);
    }

    public function testMovementConsumptionUpdatesTheBarAndEveryLinkedBubble(): void
    {
        $resources = TokenResourceValidator::stored([]);
        $resources['bubbles'][0]['linkedBarIndex'] = 1;
        $resources['bubbles'][1]['linkedBarIndex'] = 1;

        $result = TokenMovementResource::fromMovement($resources, 8, 2.5);

        $this->assertSame(5.5, $result['bars'][1]['value']);
        $this->assertSame(8.0, $result['bars'][1]['max']);
        $this->assertSame(5.5, $result['bubbles'][0]['value']);
        $this->assertSame(5.5, $result['bubbles'][1]['value']);
    }

    public function testMovementControlsCannotBeChangedAsAnOrdinaryResource(): void
    {
        $previous = TokenResourceValidator::stored([]);
        $resources = TokenMovementResource::fromMovement($previous, 8, 2);
        $resources['bubbles'][0]['linkedBarIndex'] = 1;

        $this->assertTrue(TokenMovementResource::controlsChanged(
            $previous,
            $resources,
            8,
            2
        ));
        $this->assertFalse(TokenMovementResource::controlsChanged(
            $previous,
            TokenMovementResource::fromMovement($previous, 8, 2),
            8,
            2
        ));
    }

    public function testLinkingABubbleToHealthDoesNotChangeMovementControls(): void
    {
        $previous = TokenMovementResource::fromMovement(
            TokenResourceValidator::stored([]),
            8,
            2
        );
        $resources = $previous;
        $resources['bubbles'][0]['linkedBarIndex'] = 0;

        $this->assertFalse(TokenMovementResource::controlsChanged(
            $previous,
            $resources,
            8,
            2
        ));
    }
}
