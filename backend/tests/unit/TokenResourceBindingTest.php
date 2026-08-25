<?php

use App\Services\Token\TokenResourceBinding;
use App\Services\Token\TokenResourceValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenResourceBindingTest extends CIUnitTestCase
{
    public function testNewBindingReadsActorThenTokenUpdatesActor(): void
    {
        $resources = TokenResourceValidator::stored([]);
        $resources['bars'][0] = array_merge($resources['bars'][0], [
            'enabled' => true, 'value' => 1,
            'attributePath' => 'attributes.actual.hp',
        ]);
        $actor = ['attributes' => ['actual' => ['hp' => 9]]];
        $bound = TokenResourceBinding::tokenToActor([], $resources, $actor);
        $this->assertSame(9.0, $bound['resources']['bars'][0]['value']);
        $this->assertFalse($bound['characterChanged']);

        $changed = $bound['resources'];
        $changed['bars'][0]['value'] = 6;
        $updated = TokenResourceBinding::tokenToActor(
            $bound['resources'],
            $changed,
            $actor
        );
        $this->assertSame(6.0, $updated['characterData']['attributes']['actual']['hp']);
        $this->assertTrue($updated['characterChanged']);
    }

    public function testActorChangeUpdatesEveryBoundDisplayValue(): void
    {
        $resources = TokenResourceValidator::stored([]);
        $resources['bubbles'][0]['attributePath'] = 'attributes.actual.armor';
        $result = TokenResourceBinding::actorToToken(
            $resources,
            ['attributes' => ['actual' => ['armor' => 4]]]
        );

        $this->assertTrue($result['changed']);
        $this->assertSame(4.0, $result['resources']['bubbles'][0]['value']);
    }
}
