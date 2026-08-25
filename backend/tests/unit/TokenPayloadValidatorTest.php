<?php

use App\Services\Token\TokenPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class TokenPayloadValidatorTest extends CIUnitTestCase
{
    public function testNormalizesCreatePayload(): void
    {
        $result = (new TokenPayloadValidator())->create([
            'characterId' => '12',
            'name' => '  Strażnik  ',
            'imageUrl' => 'https://assets.example.test/guard.webp',
            'x' => '120.5',
            'y' => 240,
            'width' => 80,
            'height' => 80,
            'rotation' => 370,
            'facing' => -15,
            'disposition' => 'HOSTILE',
            'hidden' => 'false',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(12, $result['data']['character_id']);
        $this->assertSame('Strażnik', $result['data']['name']);
        $this->assertSame('hostile', $result['data']['disposition']);
        $this->assertSame(0, $result['data']['hidden']);
        $this->assertSame(10.0, $result['data']['rotation']);
        $this->assertSame(345.0, $result['data']['facing']);
    }

    public function testUpdateRequiresRevisionAndAWritableField(): void
    {
        $result = (new TokenPayloadValidator())->update([]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }

    public function testRejectsUnsafeUrlAndServerOwnedFields(): void
    {
        $result = (new TokenPayloadValidator())->create([
            'name' => 'Unsafe',
            'imageUrl' => 'javascript:alert(1)',
            'campaignId' => 99,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('imageUrl', $result['errors']);
        $this->assertArrayHasKey('campaignId', $result['errors']);
    }

    public function testAcceptsEncodedHttpsImageUrlAndEmptyFallback(): void
    {
        $validator = new TokenPayloadValidator();
        $imageUrl = 'https://5e.tools/img/bestiary/tokens/DMG/Avatar%20of%20Death.webp';

        $withImage = $validator->create(['name' => 'Avatar of Death', 'imageUrl' => $imageUrl]);
        $withoutImage = $validator->create(['name' => 'Nameless', 'imageUrl' => '']);

        $this->assertTrue($withImage['valid']);
        $this->assertSame($imageUrl, $withImage['data']['image_url']);
        $this->assertTrue($withoutImage['valid']);
        $this->assertNull($withoutImage['data']['image_url']);
    }
}
