<?php

use App\Services\Character\CharacterPayloadValidator;
use CodeIgniter\Test\CIUnitTestCase;

final class CharacterPayloadValidatorTest extends CIUnitTestCase
{
    private $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new CharacterPayloadValidator();
    }

    public function testValidUpdatePreservesExtensibleCharacterData(): void
    {
        $result = $this->validator->validateUpdate([
            'name' => '  Enguerrand  ',
            'data' => [
                'details' => ['race' => 'human'],
                'attributes' => ['actual' => ['ww' => 31]],
            ],
            'updatedAt' => '2026-08-19 12:00:00',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Enguerrand', $result['data']['name']);
        $this->assertSame(31, $result['data']['data']['attributes']['actual']['ww']);
        $this->assertSame('2026-08-19 12:00:00', $result['expectedUpdatedAt']);
    }

    public function testIdentityAndOwnershipManipulationIsRejected(): void
    {
        $result = $this->validator->validateUpdate([
            'name' => 'Safe name',
            'campaign_id' => 99,
            'user_id' => 4,
            'brass' => 999999,
        ]);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('campaign_id', $result['errors']['payload']);
        $this->assertStringContainsString('user_id', $result['errors']['payload']);
        $this->assertStringContainsString('brass', $result['errors']['payload']);
    }

    public function testUnsafeJsonKeyIsRejected(): void
    {
        $result = $this->validator->validateUpdate([
            'data' => ['details' => ['__proto__' => ['admin' => true]]],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame('Character data contains an unsafe key.', $result['errors']['data']);
    }

    public function testConflictingAliasesAreRejected(): void
    {
        $result = $this->validator->validateUpdate([
            'avatarUrl' => '/safe/avatar.webp',
            'avatar_url' => '/different/avatar.webp',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('avatarUrl', $result['errors']);
    }

    public function testArrayAndObjectScalarFieldsAreRejectedWithoutCasting(): void
    {
        $result = $this->validator->validateUpdate([
            'name' => ['Array is not a name'],
            'avatarUrl' => (object) ['url' => '/avatar.webp'],
            'updatedAt' => ['2026-08-19 12:00:00'],
        ]);

        $this->assertFalse($result['valid']);
        $this->assertSame('Name must be a string.', $result['errors']['name']);
        $this->assertSame(
            'Avatar must be a string or null.',
            $result['errors']['avatarUrl']
        );
        $this->assertSame(
            'The character version must be a string or null.',
            $result['errors']['updatedAt']
        );
    }

    public function testCreateRequiresCampaignSystemUniverseAndName(): void
    {
        $result = $this->validator->validateCreate(['name' => 'A']);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('campaignId', $result['errors']);
        $this->assertArrayHasKey('systemId', $result['errors']);
        $this->assertArrayHasKey('universeId', $result['errors']);
        $this->assertArrayHasKey('name', $result['errors']);
    }
}
