<?php

use App\Services\Light\LightPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class LightPayloadValidatorTest extends CIUnitTestCase
{
    public function testCreatesLightWithSafeDefaults(): void
    {
        $result = (new LightPayloadValidator())->create(['x' => 100, 'y' => 200]);

        $this->assertTrue($result['valid']);
        $this->assertSame(200, $result['data']['bright_radius']);
        $this->assertSame(400, $result['data']['dim_radius']);
        $this->assertSame('#FFD27A', $result['data']['color']);
    }

    public function testRejectsInvalidRadiiColorAndOwnedFields(): void
    {
        $result = (new LightPayloadValidator())->create([
            'x' => 10,
            'y' => 20,
            'brightRadius' => 500,
            'dimRadius' => 100,
            'color' => 'red',
            'campaignId' => 7,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('dimRadius', $result['errors']);
        $this->assertArrayHasKey('color', $result['errors']);
        $this->assertArrayHasKey('campaignId', $result['errors']);
    }

    public function testUpdateRequiresRevisionAndWritableField(): void
    {
        $result = (new LightPayloadValidator())->update(['revision' => 1]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }
}
