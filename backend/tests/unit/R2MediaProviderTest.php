<?php

use App\Services\Media\R2MediaProvider;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class R2MediaProviderTest extends CIUnitTestCase
{
    public function testBuildsDeterministicSigV4PutUrl(): void
    {
        $provider = new R2MediaProvider(
            'https://account.r2.cloudflarestorage.com',
            'blatyrpg',
            'access-key',
            'secret-key'
        );
        $url = $provider->presignedUrl('PUT', 'media/2026/09/7/file name.pdf', 900, 1_797_811_200);

        $this->assertStringStartsWith(
            'https://account.r2.cloudflarestorage.com/blatyrpg/media/2026/09/7/file%20name.pdf?',
            $url
        );
        $this->assertStringContainsString('X-Amz-Algorithm=AWS4-HMAC-SHA256', $url);
        $this->assertStringContainsString('X-Amz-Expires=900', $url);
        $this->assertMatchesRegularExpression('/X-Amz-Signature=[a-f0-9]{64}/', $url);
    }

    public function testUsesCustomDomainOnlyForPublicDelivery(): void
    {
        $provider = new R2MediaProvider(
            'https://account.r2.cloudflarestorage.com',
            'blatyrpg-private',
            'access-key',
            'secret-key',
            'https://media.example.test',
            null,
            'blatyrpg-public'
        );
        $url = $provider->deliveryUrl([
            'provider_container' => 'blatyrpg-public',
            'provider_asset_id' => 'media/7/file name.pdf',
            'visibility' => 'public',
        ]);

        $this->assertSame('https://media.example.test/media/7/file%20name.pdf', $url);

        $privateUrl = $provider->deliveryUrl([
            'provider_container' => 'blatyrpg-private',
            'provider_asset_id' => 'media/7/file name.pdf',
            'visibility' => 'public',
        ]);
        $this->assertStringContainsString('/blatyrpg-private/media/7/file%20name.pdf?', $privateUrl);
    }

    public function testRoutesVisibilityToSeparateBuckets(): void
    {
        $provider = new R2MediaProvider(
            'https://account.eu.r2.cloudflarestorage.com',
            'blatyrpg-media-private',
            'access-key',
            'secret-key',
            null,
            null,
            'blatyrpg-media-public'
        );

        $this->assertSame(
            'blatyrpg-media-public',
            $provider->storageContainer(['visibility' => 'public'])
        );
        $this->assertSame(
            'blatyrpg-media-private',
            $provider->storageContainer(['visibility' => 'campaign'])
        );
        $this->assertSame(
            'blatyrpg-media-private',
            $provider->storageContainer(['visibility' => 'private'])
        );

        $upload = $provider->prepareUpload([
            'visibility' => 'public',
            'provider_asset_id' => 'media/public/example.ogg',
            'mime_type' => 'audio/ogg',
        ]);
        $this->assertStringContainsString(
            '/blatyrpg-media-public/media/public/example.ogg?',
            $upload['url']
        );
    }

    public function testCompletesUploadOnlyAfterRemoteMetadataMatches(): void
    {
        $provider = new R2MediaProvider(
            'https://account.r2.cloudflarestorage.com',
            'blatyrpg',
            'access-key',
            'secret-key',
            null,
            static fn (string $key, string $bucket): array => [
                'etag' => $key === 'media/7/ambience.ogg' && $bucket === 'blatyrpg'
                    ? 'verified-etag' : '',
                'file_size' => 2048,
            ]
        );
        $completed = $provider->completeUpload([
            'provider_container' => 'blatyrpg',
            'provider_asset_id' => 'media/7/ambience.ogg',
            'mime_type' => 'audio/ogg',
            'file_size' => 2048,
        ], [
            'eTag' => 'verified-etag',
            'fileSize' => 2048,
        ]);

        $this->assertSame('blatyrpg', $completed['provider_container']);
        $this->assertSame('media/7/ambience.ogg', $completed['provider_asset_id']);
        $this->assertSame(2048, $completed['file_size']);
        $this->assertSame('verified-etag', $completed['metadata']['etag']);
    }
}
