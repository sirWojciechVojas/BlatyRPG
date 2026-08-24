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
}
