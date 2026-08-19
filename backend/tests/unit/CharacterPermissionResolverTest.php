<?php

use App\Services\Character\CharacterPermissionResolver;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterPermissionResolverTest extends CIUnitTestCase
{
    public function testExplicitPermissionOverridesLegacyShopClaim(): void
    {
        $levels = CharacterPermissionResolver::levelsFromRows(
            [
                ['resource_id' => 7, 'access_level' => 'none'],
                ['resource_id' => 8, 'access_level' => 'observer'],
                ['resource_id' => 9, 'access_level' => 'invalid'],
            ],
            [
                ['character_id' => 7],
                ['character_id' => 8],
                ['character_id' => 9],
                ['character_id' => 10],
            ]
        );

        $this->assertSame('none', $levels[7]);
        $this->assertSame('observer', $levels[8]);
        $this->assertSame('none', $levels[9]);
        $this->assertSame('owner', $levels[10]);
    }
}
