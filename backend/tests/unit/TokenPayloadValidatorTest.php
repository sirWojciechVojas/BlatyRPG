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
            'rotationHandleEnabled' => true,
            'facingHandleEnabled' => false,
            'movementRange' => 8.5,
            'movementSpent' => 2,
            'movementResetMode' => 'round',
            'showInfoUnselected' => true,
            'visibleTo' => ['mode' => 'users', 'userIds' => [7, '4', 7]],
            'controlledBy' => ['mode' => 'everyone', 'userIds' => []],
            'statuses' => ['poisoned', 'stunned', 'poisoned'],
            'resources' => ['bars' => [[
                'enabled' => true, 'label' => 'HP', 'value' => 7, 'max' => 10,
                'attributePath' => 'attributes.actual.hp',
            ]]],
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
        $this->assertSame(1, $result['data']['rotation_handle_enabled']);
        $this->assertSame(0, $result['data']['facing_handle_enabled']);
        $this->assertSame(8.5, $result['data']['movement_range']);
        $this->assertSame(2.0, $result['data']['movement_spent']);
        $this->assertSame('round', $result['data']['movement_reset_mode']);
        $this->assertSame(1, $result['data']['show_info_unselected']);
        $this->assertSame(
            ['mode' => 'users', 'userIds' => [4, 7]],
            $result['data']['visible_to_json']
        );
        $this->assertSame(
            ['mode' => 'everyone', 'userIds' => []],
            $result['data']['controlled_by_json']
        );
        $this->assertSame(['poisoned', 'stunned'], $result['data']['statuses_json']);
        $this->assertCount(4, $result['data']['bars_json']['bars']);
    }

    public function testUpdateRequiresRevisionAndAWritableField(): void
    {
        $result = (new TokenPayloadValidator())->update([]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('revision', $result['errors']);
        $this->assertArrayHasKey('payload', $result['errors']);
    }

    public function testAcceptsQuarterCellDimensionsOnTheSmallestGrid(): void
    {
        $result = (new TokenPayloadValidator())->create([
            'name' => 'Duszek',
            'width' => 1,
            'height' => 1,
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame(1.0, $result['data']['width']);
        $this->assertSame(1.0, $result['data']['height']);
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
        $this->assertArrayNotHasKey('width', $withoutImage['data']);
        $this->assertArrayNotHasKey('height', $withoutImage['data']);
    }

    public function testRejectsMalformedPermissionScopes(): void
    {
        $result = (new TokenPayloadValidator())->update([
            'revision' => 2,
            'editableBy' => ['mode' => 'everyone', 'userIds' => [9]],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('editableBy', $result['errors']);
    }

    public function testRejectsInvalidMovementConfiguration(): void
    {
        $result = (new TokenPayloadValidator())->update([
            'revision' => 2,
            'movementRange' => -1,
            'movementResetMode' => 'session',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('movementRange', $result['errors']);
        $this->assertArrayHasKey('movementResetMode', $result['errors']);
    }
}
