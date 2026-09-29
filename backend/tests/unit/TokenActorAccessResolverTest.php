<?php

use App\Services\Token\TokenActorAccessResolver;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockConnection;

/** @internal */
final class TokenActorAccessResolverTest extends CIUnitTestCase
{
    public function testCharacterLookupUsesTheCurrentCharactersSchema(): void
    {
        $db = new MockConnection([
            'DBDriver' => 'Mock',
            'database' => 'test',
            'DBPrefix' => '',
        ]);
        $db->shouldReturn('execute', (object) []);
        $resolver = new TokenActorAccessResolver($db);

        $this->assertFalse($resolver->canControl(
            ['user_id' => 12],
            5,
            ['character_id' => 39]
        ));

        $sql = (string) $db->getLastQuery();
        $this->assertMatchesRegularExpression('/FROM ["`]characters["`]/', $sql);
        $this->assertStringNotContainsString('deleted_at', $sql);
    }
}
