<?php

namespace Tests\Unit;

use App\Services\Magic\Wfrp2MagicResolver;
use CodeIgniter\Test\CIUnitTestCase;

final class Wfrp2MagicResolverTest extends CIUnitTestCase
{
    public function testSuccessAndMinorManifestationAreIndependent(): void
    {
        $result = (new Wfrp2MagicResolver())->resolveResults([6, 6], [], 12);

        $this->assertTrue($result['spellSucceeded']);
        $this->assertFalse($result['automaticFailure']);
        $this->assertSame('minor', $result['manifestations'][0]['severity']);
    }

    public function testTripleIsOneMajorAndNotAnAdditionalPair(): void
    {
        $result = (new Wfrp2MagicResolver())->resolveResults([5, 5, 5], [], 12);

        $this->assertCount(1, $result['manifestations']);
        $this->assertSame('major', $result['manifestations'][0]['severity']);
    }

    public function testTwoDifferentPairsProduceTwoMinorManifestations(): void
    {
        $result = (new Wfrp2MagicResolver())->resolveResults([2, 2, 6, 6], [], 12);

        $this->assertSame(['minor', 'minor'], array_column($result['manifestations'], 'severity'));
    }

    public function testAllOnesAutomaticallyFailEvenWithEnoughModifier(): void
    {
        $result = (new Wfrp2MagicResolver())->resolveResults([1], [], 3, 10);

        $this->assertTrue($result['automaticFailure']);
        $this->assertTrue($result['willpowerTestRequired']);
        $this->assertFalse($result['spellSucceeded']);
    }

    public function testChaosDieCanCauseCurseButNeverAddsPower(): void
    {
        $result = (new Wfrp2MagicResolver())->resolveResults([6], [6], 12, 6);

        $this->assertSame(12, $result['powerTotal']);
        $this->assertTrue($result['spellSucceeded']);
        $this->assertSame('minor', $result['manifestations'][0]['severity']);
        $this->assertFalse($result['dice'][1]['includedInPower']);
    }
}
