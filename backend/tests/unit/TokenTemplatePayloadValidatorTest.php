<?php

use App\Services\Token\TokenTemplatePayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenTemplatePayloadValidatorTest extends CIUnitTestCase
{
    public function testNormalizesReusableTemplateConfiguration(): void
    {
        $result = (new TokenTemplatePayloadValidator())->create([
            'name' => '  Strażnik  ',
            'imageUrl' => 'https://assets.example.test/guard.webp',
            'widthCells' => 1.5,
            'heightCells' => 2,
            'rotation' => 370,
            'facing' => -15,
            'rotationHandleEnabled' => true,
            'facingHandleEnabled' => false,
            'rotationFollowsFacing' => true,
            'showInfoUnselected' => true,
            'resourceBarPosition' => 'above',
            'elevation' => 3,
            'disposition' => 'HOSTILE',
            'movementRange' => 8,
            'movementResetMode' => 'round',
            'resources' => ['bars' => [[
                'enabled' => true, 'label' => 'HP', 'value' => 8, 'max' => 10,
            ]]],
            'vision' => ['enabled' => true, 'range' => 600],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Strażnik', $result['data']['name']);
        $this->assertSame(1.5, $result['data']['width_cells']);
        $this->assertSame(10.0, $result['data']['rotation']);
        $this->assertSame(345.0, $result['data']['facing']);
        $this->assertSame('hostile', $result['data']['disposition']);
        $this->assertSame('round', $result['data']['movement_reset_mode']);
        $this->assertCount(4, $result['data']['bars_json']['bars']);
        $this->assertTrue($result['data']['vision_json']['enabled']);
    }

    public function testAcceptsUploadInsteadOfUrl(): void
    {
        $result = (new TokenTemplatePayloadValidator())->create([
            'name' => 'Uploaded',
        ], true);

        $this->assertTrue($result['valid']);
        $this->assertArrayNotHasKey('image_url', $result['data']);
    }

    public function testRejectsRuntimeAndPermissionFields(): void
    {
        $result = (new TokenTemplatePayloadValidator())->create([
            'name' => 'Runtime state',
            'imageUrl' => 'https://assets.example.test/token.png',
            'characterId' => 4,
            'movementSpent' => 2,
            'statuses' => ['poisoned'],
            'hidden' => true,
            'locked' => true,
            'visibleTo' => ['mode' => 'gm', 'userIds' => []],
            'x' => 10,
            'y' => 20,
        ]);

        $this->assertFalse($result['valid']);
        foreach (['characterId', 'movementSpent', 'statuses', 'hidden', 'locked', 'visibleTo', 'x', 'y'] as $field) {
            $this->assertArrayHasKey($field, $result['errors']);
        }
    }

    public function testRequiresOneImageSourceAndOptimisticRevision(): void
    {
        $missing = (new TokenTemplatePayloadValidator())->create(['name' => 'No image']);
        $both = (new TokenTemplatePayloadValidator())->create([
            'name' => 'Two images',
            'imageUrl' => 'https://assets.example.test/token.png',
        ], true);
        $update = (new TokenTemplatePayloadValidator())->update(['name' => 'Changed']);

        $this->assertFalse($missing['valid']);
        $this->assertArrayHasKey('image', $missing['errors']);
        $this->assertFalse($both['valid']);
        $this->assertArrayHasKey('image', $both['errors']);
        $this->assertFalse($update['valid']);
        $this->assertArrayHasKey('revision', $update['errors']);
    }

    public function testRejectsNonHttpsAndMalformedImageUrls(): void
    {
        foreach (['http://assets.example.test/token.png', 'javascript:alert(1)', '/local.png'] as $url) {
            $result = (new TokenTemplatePayloadValidator())->create([
                'name' => 'Unsafe image',
                'imageUrl' => $url,
            ]);
            $this->assertFalse($result['valid']);
            $this->assertArrayHasKey('imageUrl', $result['errors']);
        }
    }
}
