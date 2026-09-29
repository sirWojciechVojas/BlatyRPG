<?php

namespace Tests\Unit;

use App\Services\Media\ExternalMediaUrl;
use CodeIgniter\Test\CIUnitTestCase;

final class ExternalMediaUrlTest extends CIUnitTestCase
{
    public function testCanonicalizesHttpSourcesAndIgnoresFragmentsForDeduplication(): void
    {
        $url = 'https://cdn.example.test/maps/library.webp?size=large#preview';

        $canonical = ExternalMediaUrl::canonicalize($url);

        $this->assertSame('https://cdn.example.test/maps/library.webp?size=large', $canonical);
        $this->assertSame(
            ExternalMediaUrl::fingerprint($canonical),
            ExternalMediaUrl::fingerprint('https://cdn.example.test/maps/library.webp?size=large')
        );
        $this->assertSame('library.webp', ExternalMediaUrl::filename($canonical));
    }

    public function testRejectsRelativeCredentialedAndUnsafeProtocols(): void
    {
        foreach ([
            '/api/campaigns/5/scene-assets/file.webp/file',
            '//cdn.example.test/map.webp',
            'javascript:alert(1)',
            'https://user:secret@cdn.example.test/map.webp',
        ] as $url) {
            $this->assertNull(ExternalMediaUrl::canonicalize($url));
        }
    }
}
