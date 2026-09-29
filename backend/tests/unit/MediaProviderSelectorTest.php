<?php

use App\Services\Media\MediaException;
use App\Services\Media\MediaProviderSelector;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class MediaProviderSelectorTest extends CIUnitTestCase
{
    public function testRoutesImagesAndHeavyFilesToExpectedProviders(): void
    {
        $selector = new MediaProviderSelector();

        $this->assertSame('cloudinary', $selector->select('portrait', 'image/webp', 1024));
        $this->assertSame('cloudinary', $selector->select('map', 'image/png', 25 * 1024 * 1024));
        $this->assertSame('r2', $selector->select('large-map', 'image/png', 1024));
        $this->assertSame('r2', $selector->select('map', 'image/png', 25 * 1024 * 1024 + 1));
        $this->assertSame('r2', $selector->select('map-creator', 'image/png', 25 * 1024 * 1024 + 1));
        $this->assertSame('r2', $selector->select('audio', 'audio/ogg', 1024));
        $this->assertSame('r2', $selector->select('pdf', 'application/pdf', 1024));
    }

    public function testRejectsNonImageForImageOnlyCategory(): void
    {
        $this->expectException(MediaException::class);
        (new MediaProviderSelector())->select('token', 'application/pdf', 1024);
    }
}
