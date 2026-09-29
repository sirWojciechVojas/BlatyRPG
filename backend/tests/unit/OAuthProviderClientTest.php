<?php

use App\Services\Auth\OAuthProviderClient;
use App\Services\Auth\OAuthProviderRegistry;
use CodeIgniter\Test\CIUnitTestCase;

final class OAuthProviderClientTest extends CIUnitTestCase
{
    private function client(): OAuthProviderClient
    {
        return new OAuthProviderClient(new OAuthProviderRegistry([
            'OAUTH_CALLBACK_BASE_URL' => 'https://rpg.example/api/auth/oauth',
            'OAUTH_FRONTEND_CALLBACK_URL' => 'https://rpg.example/#/oauth/callback',
            'GOOGLE_OAUTH_CLIENT_ID' => 'client',
            'GOOGLE_OAUTH_CLIENT_SECRET' => 'secret',
        ]));
    }

    public function testGoogleIdentityRequiresProviderVerification(): void
    {
        $identity = $this->client()->normalizeIdentity('google', [
            'sub' => 'google-123',
            'email' => 'Player@Example.test',
            'email_verified' => true,
            'name' => 'Player One',
            'picture' => 'https://images.example/player.png',
        ]);

        $this->assertSame('google-123', $identity['subject']);
        $this->assertSame('player@example.test', $identity['email']);
        $this->assertTrue($identity['email_verified']);
        $this->assertSame('https://images.example/player.png', $identity['avatar_url']);
    }

    public function testDiscordAvatarUsesTheAuthenticatedSubject(): void
    {
        $identity = $this->client()->normalizeIdentity('discord', [
            'id' => '987',
            'email' => 'player@example.test',
            'verified' => true,
            'global_name' => 'Player',
            'avatar' => 'avatar-hash',
        ]);

        $this->assertSame(
            'https://cdn.discordapp.com/avatars/987/avatar-hash.png?size=256',
            $identity['avatar_url']
        );
    }
}
