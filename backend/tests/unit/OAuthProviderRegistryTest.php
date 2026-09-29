<?php

use App\Services\Auth\AuthException;
use App\Services\Auth\OAuthProviderRegistry;
use CodeIgniter\Test\CIUnitTestCase;

final class OAuthProviderRegistryTest extends CIUnitTestCase
{
    public function testOnlyFullyConfiguredProvidersAreEnabled(): void
    {
        $registry = new OAuthProviderRegistry([
            'OAUTH_CALLBACK_BASE_URL' => 'https://rpg.example/api/auth/oauth',
            'OAUTH_FRONTEND_CALLBACK_URL' => 'https://rpg.example/#/oauth/callback',
            'GOOGLE_OAUTH_CLIENT_ID' => 'google-client',
            'GOOGLE_OAUTH_CLIENT_SECRET' => 'google-secret',
        ]);

        $providers = array_column($registry->summaries(), null, 'provider');
        $this->assertTrue($providers['google']['enabled']);
        $this->assertFalse($providers['facebook']['enabled']);
        $this->assertArrayNotHasKey('client_secret', $providers['google']);
        $this->assertSame(
            'https://rpg.example/api/auth/oauth/google/callback',
            $registry->configuration('google')['redirect_uri']
        );
    }

    public function testUnavailableProviderFailsClosed(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionMessage('OAuth provider is not configured.');
        (new OAuthProviderRegistry([]))->configuration('discord');
    }
}
