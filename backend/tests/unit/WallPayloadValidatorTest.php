<?php

use App\Services\Wall\WallPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class WallPayloadValidatorTest extends CIUnitTestCase
{
    public function testCreatesClosedDoorWithBlockingDefaults(): void
    {
        $result = (new WallPayloadValidator())->create([
            'type' => 'door',
            'x1' => 10,
            'y1' => 20,
            'x2' => 80,
            'y2' => 20,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('closed', $result['data']['door_state']);
        $this->assertSame(1, $result['data']['blocks_movement']);
    }

    public function testAcceptsWindowWithTransparentSightAndLight(): void
    {
        $result = (new WallPayloadValidator())->create([
            'type' => 'window',
            'x1' => 20,
            'y1' => 40,
            'x2' => 80,
            'y2' => 40,
            'blocksMovement' => true,
            'blocksSight' => false,
            'blocksLight' => false,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('window', $result['data']['type']);
        $this->assertSame(1, $result['data']['blocks_movement']);
        $this->assertSame(0, $result['data']['blocks_sight']);
        $this->assertSame(0, $result['data']['blocks_light']);
        $this->assertSame('proximity', $result['data']['restriction_type']);
        $this->assertSame(10, $result['data']['proximity_threshold']);
        $this->assertSame('closed', $result['data']['door_state']);
    }

    /** @dataProvider independentPresetProvider */
    public function testAppliesIndependentWallPresetCapabilities(
        string $type,
        int $movement,
        int $sight,
        int $light,
        int $sound
    ): void {
        $result = (new WallPayloadValidator())->create([
            'type' => $type,
            'x1' => 0,
            'y1' => 0,
            'x2' => 100,
            'y2' => 0,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame($movement, $result['data']['blocks_movement']);
        $this->assertSame($sight, $result['data']['blocks_sight']);
        $this->assertSame($light, $result['data']['blocks_light']);
        $this->assertSame($sound, $result['data']['blocks_sound']);
    }

    public static function independentPresetProvider(): array
    {
        return [
            'solid' => ['wall', 1, 1, 1, 1],
            'invisible' => ['invisible', 1, 0, 0, 0],
            'ethereal' => ['ethereal', 0, 1, 1, 0],
        ];
    }

    public function testRejectsZeroLengthAndServerOwnedFields(): void
    {
        $result = (new WallPayloadValidator())->create([
            'x1' => 20,
            'y1' => 20,
            'x2' => 20,
            'y2' => 20,
            'campaignId' => 7,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('geometry', $result['errors']);
        $this->assertArrayHasKey('campaignId', $result['errors']);
    }

    public function testUpdateRequiresRevisionAndWritableField(): void
    {
        $result = (new WallPayloadValidator())->update(['revision' => 1]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }

    public function testAcceptsPresentationAndActivationSettings(): void
    {
        $result = (new WallPayloadValidator())->update([
            'revision' => 2,
            'name' => 'North gate',
            'color' => '#33aaff',
            'enabled' => false,
            'hidden' => true,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('#33AAFF', $result['data']['color']);
        $this->assertSame(0, $result['data']['enabled']);
        $this->assertSame(1, $result['data']['hidden']);
    }

    public function testAcceptsValidatedWallSoundRules(): void
    {
        $result = (new WallPayloadValidator())->update([
            'revision' => 2,
            'soundConfig' => [
                'version' => 2,
                'rules' => [[
                    'id' => 'ambient-1',
                    'enabled' => true,
                    'trigger' => 'proximityLoop',
                    'trackId' => 8,
                    'volume' => 1,
                    'fadeInMs' => 100,
                    'fadeOutMs' => 200,
                    'range' => 15,
                    'zoneCount' => 4,
                    'geometry' => [
                        'mode' => 'offsetLine',
                        'points' => [0.5],
                        'offset' => -2,
                    ],
                ]],
            ],
        ]);

        $this->assertTrue($result['valid']);
        $config = json_decode($result['data']['sound_config_json'], true);
        $this->assertSame(4, $config['rules'][0]['zoneCount']);
    }
}
