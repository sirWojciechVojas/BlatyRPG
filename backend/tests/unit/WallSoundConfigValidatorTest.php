<?php

use App\Services\Wall\WallSoundConfigValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class WallSoundConfigValidatorTest extends CIUnitTestCase
{
    private function rule(array $changes = []): array
    {
        return array_merge([
            'id' => 'rule-1',
            'enabled' => true,
            'trigger' => 'proximityLoop',
            'trackId' => 7,
            'volume' => 0.8,
            'fadeInMs' => 250,
            'fadeOutMs' => 350,
            'range' => 12,
            'zoneCount' => 3,
            'geometry' => [
                'mode' => 'points',
                'points' => [0.25, 0.75],
                'offset' => 0,
            ],
        ], $changes);
    }

    public function testAcceptsVersionedSpatialRules(): void
    {
        $result = (new WallSoundConfigValidator())->validate([
            'version' => 2,
            'rules' => [$this->rule()],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame([7], $result['trackIds']);
        $this->assertSame(3, $result['config']['rules'][0]['zoneCount']);
    }

    /** @dataProvider invalidRuleProvider */
    public function testRejectsUnsafeOrOutOfRangeRules(array $changes, string $error): void
    {
        $result = (new WallSoundConfigValidator())->validate([
            'version' => 2,
            'rules' => [$this->rule($changes)],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey($error, $result['errors']);
    }

    public static function invalidRuleProvider(): array
    {
        return [
            'too many zones' => [['zoneCount' => 13], 'soundConfig.rules.0.zoneCount'],
            'missing track' => [['trackId' => null], 'soundConfig.rules.0.trackId'],
            'legacy arbitrary URL' => [[
                'legacyUrl' => 'https://invalid.example/file.ogg',
            ], 'soundConfig.rules.0'],
            'point outside wall' => [[
                'geometry' => ['mode' => 'points', 'points' => [1.2], 'offset' => 0],
            ], 'soundConfig.rules.0.geometry.points.0'],
        ];
    }

    public function testSupportsTwelveAutomaticallyGeneratedZones(): void
    {
        $result = (new WallSoundConfigValidator())->validate([
            'version' => 2,
            'rules' => [$this->rule(['zoneCount' => 12, 'range' => 60])],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(12, $result['config']['rules'][0]['zoneCount']);
        $this->assertSame(60.0, $result['config']['rules'][0]['range']);
    }
}

