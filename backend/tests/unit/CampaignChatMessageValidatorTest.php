<?php

use App\Services\Chat\CampaignChatMessageValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignChatMessageValidatorTest extends CIUnitTestCase
{
    public function testNormalizesBodyAndClientNonce(): void
    {
        $result = (new CampaignChatMessageValidator())->validate([
            'body' => "  Witaj\r\nDrużyno  ",
            'clientNonce' => '550E8400-E29B-41D4-A716-446655440000',
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame("Witaj\nDrużyno", $result['data']['body']);
        $this->assertSame(
            '550e8400-e29b-41d4-a716-446655440000',
            $result['data']['client_nonce']
        );
    }

    public function testRejectsIdentityManipulationAndEmptyBody(): void
    {
        $result = (new CampaignChatMessageValidator())->validate([
            'body' => '   ',
            'author_user_id' => 1,
            'message_type' => 'system',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('body', $result['errors']);
        $this->assertArrayHasKey('author_user_id', $result['errors']);
        $this->assertArrayHasKey('message_type', $result['errors']);
    }

    public function testRejectsOversizedAndControlCharacterMessages(): void
    {
        $validator = new CampaignChatMessageValidator();
        $tooLong = $validator->validate([
            'body' => str_repeat('ą', CampaignChatMessageValidator::MAX_LENGTH + 1),
        ]);
        $control = $validator->validate(['body' => "hello\x00world"]);

        $this->assertFalse($tooLong['valid']);
        $this->assertFalse($control['valid']);
        $this->assertArrayHasKey('body', $tooLong['errors']);
        $this->assertArrayHasKey('body', $control['errors']);
    }

    public function testRequiresIdempotencyNonce(): void
    {
        $result = (new CampaignChatMessageValidator())->validate([
            'body' => 'Ready',
        ]);

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('clientNonce', $result['errors']);
    }
}
