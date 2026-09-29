<?php

namespace Tests\Unit;

use App\Services\Media\ExternalMediaProvider;
use App\Services\Media\MediaException;
use CodeIgniter\Test\CIUnitTestCase;

final class ExternalMediaProviderTest extends CIUnitTestCase
{
    public function testDeliversTheRegisteredUrlWithoutAProviderRequest(): void
    {
        $provider = new ExternalMediaProvider();
        $asset = [
            'source_url' => 'https://cdn.example.test/maps/library.webp#preview',
            'availability_status' => 'unknown',
        ];

        $this->assertSame(
            'https://cdn.example.test/maps/library.webp',
            $provider->deliveryUrl($asset)
        );
        $this->assertSame('unknown', $provider->verifyObject($asset)['availability_status']);
    }

    public function testRejectsWritesBecauseExternalStorageIsReadOnly(): void
    {
        $this->expectException(MediaException::class);
        (new ExternalMediaProvider())->prepareUpload([]);
    }
}
