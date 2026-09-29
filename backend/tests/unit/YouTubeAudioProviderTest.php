<?php

use App\Services\Audio\YouTubeAudioProvider;
use App\Services\Campaign\CampaignException;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class YouTubeAudioProviderTest extends CIUnitTestCase
{
    public function testItNormalizesSupportedYouTubeUrlsWithoutResolvingStreams(): void
    {
        $result = (new YouTubeAudioProvider())->normalize(
            'https://www.youtube.com/watch?v=dQw4w9WgXcQ&list=ignored'
        );

        $this->assertSame('youtube', $result['provider']);
        $this->assertSame('dQw4w9WgXcQ', $result['reference']);
        $this->assertSame('https://www.youtube.com/watch?v=dQw4w9WgXcQ', $result['url']);
        $this->assertStringContainsString('dQw4w9WgXcQ', $result['thumbnailUrl']);
    }

    public function testItRejectsLookalikeHostsAndUnsupportedUrls(): void
    {
        $this->expectException(CampaignException::class);
        (new YouTubeAudioProvider())->normalize('https://youtube.com.attacker.example/watch?v=dQw4w9WgXcQ');
    }
}
