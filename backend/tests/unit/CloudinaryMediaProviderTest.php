<?php

use App\Services\Media\CloudinaryMediaProvider;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CloudinaryMediaProviderTest extends CIUnitTestCase
{
    public function testCreatesSignedDirectUploadWithoutReturningCredentials(): void
    {
        $provider = new CloudinaryMediaProvider('demo-cloud', 'api-key', 'api-secret');
        $this->assertSame('demo-cloud', $provider->storageContainer([]));
        $upload = $provider->prepareUpload([
            'provider_container' => 'demo-cloud',
            'public_id' => 'media/token/2026/09/example',
            'resource_type' => 'image',
            'visibility' => 'campaign',
        ]);

        $this->assertSame('POST', $upload['method']);
        $this->assertSame('multipart/form-data', $upload['encoding']);
        $this->assertStringContainsString('/demo-cloud/image/upload', $upload['url']);
        $this->assertSame('authenticated', $upload['fields']['type']);
        $this->assertSame('api-key', $upload['fields']['api_key']);
        $this->assertArrayNotHasKey('api_secret', $upload['fields']);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $upload['fields']['signature']);
    }

    public function testVerifiesUploadResponseAndBuildsCentralVariant(): void
    {
        $provider = new CloudinaryMediaProvider('demo-cloud', 'api-key', 'api-secret');
        $publicId = 'media/portrait/2026/09/example';
        $version = 1234;
        $completed = $provider->completeUpload([
            'provider_container' => 'demo-cloud',
            'public_id' => $publicId,
            'mime_type' => 'image/png',
            'file_size' => 2048,
            'visibility' => 'public',
        ], [
            'public_id' => $publicId,
            'asset_id' => 'cloudinary-asset-id',
            'version' => $version,
            'signature' => sha1('public_id=' . $publicId . '&version=' . $version . 'api-secret'),
            'format' => 'webp',
            'bytes' => 2048,
            'width' => 320,
            'height' => 480,
            'type' => 'upload',
        ]);

        $this->assertSame('cloudinary-asset-id', $completed['provider_asset_id']);
        $url = $provider->deliveryUrl([
            'provider_container' => 'demo-cloud',
            'public_id' => $publicId,
            'resource_type' => 'image',
            'visibility' => 'public',
            'metadata' => ['delivery_type' => 'upload'],
        ], 'portrait-card');
        $this->assertSame(
            'https://res.cloudinary.com/demo-cloud/image/upload/'
            . 'f_auto,q_auto,c_fill,g_auto,w_320,h_480/' . $publicId,
            $url
        );
    }
}
