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
}
