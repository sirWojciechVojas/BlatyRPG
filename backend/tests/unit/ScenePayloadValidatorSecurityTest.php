<?php

use App\Services\Scene\ScenePayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class ScenePayloadValidatorSecurityTest extends CIUnitTestCase
{
    public function testRejectsMalformedAssetUrl(): void
    {
        $result = (new ScenePayloadValidator())->validateCreate([
            'name' => 'Broken map',
            'background_url' => 'http://[',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('background_url', $result['errors']);
    }
}
