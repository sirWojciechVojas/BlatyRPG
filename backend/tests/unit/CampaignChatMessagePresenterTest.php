<?php

use App\Services\Chat\CampaignChatMessagePresenter;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CampaignChatMessagePresenterTest extends CIUnitTestCase
{
    public function testReturnsSafePublicShape(): void
    {
        $message = (new CampaignChatMessagePresenter())->present([
            'id' => '12',
            'campaign_id' => '3',
            'author_user_id' => '8',
            'author_name' => 'Lidia',
            'body' => '<img src=x onerror=alert(1)>',
            'message_type' => 'text',
            'client_nonce' => null,
            'metadata_json' => null,
            'created_at' => '2026-08-19 12:00:00',
            'password_hash' => 'must-not-leak',
        ], 8);

        $this->assertSame(12, $message['id']);
        $this->assertTrue($message['author']['isCurrentUser']);
        $this->assertSame('<img src=x onerror=alert(1)>', $message['body']);
        $this->assertArrayNotHasKey('password_hash', $message);
    }
}
