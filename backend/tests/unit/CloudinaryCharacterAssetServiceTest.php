<?php

use App\Services\CloudinaryCharacterAssetService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class CloudinaryCharacterAssetServiceTest extends CIUnitTestCase
{
    public function testBuildsCanonicalPublicIdsWithSixDigitSetId(): void
    {
        $service = new CloudinaryCharacterAssetService('demo-cloud');

        $this->assertSame(
            'character-assets/000037/portrait',
            $service->canonicalPublicId(37, 'portrait')
        );
    }

    public function testBuildsTypedCloudinaryUrlWithoutPersistedFullUrl(): void
    {
        $service = new CloudinaryCharacterAssetService('demo-cloud');

        $this->assertSame(
            'https://res.cloudinary.com/demo-cloud/image/upload/'
            . 'f_auto,q_auto,c_fill,g_auto,w_128,h_128/'
            . 'character-assets/000037/avatar',
            $service->url('character-assets/000037/avatar', 'avatar')
        );
    }

    public function testConvertsVersionedCloudinaryUrlToPublicId(): void
    {
        $service = new CloudinaryCharacterAssetService('demo-cloud');

        $this->assertSame(
            'character-assets/000037/token',
            $service->normalizePublicId(
                'https://res.cloudinary.com/demo-cloud/image/upload/c_fill,w_64/v1234/'
                . 'character-assets/000037/token.png'
            )
        );
    }

    public function testRejectsUnsupportedAssetType(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CloudinaryCharacterAssetService('demo-cloud'))
            ->canonicalPublicId(37, 'banner');
    }
}
