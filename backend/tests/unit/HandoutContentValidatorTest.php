<?php

use App\Services\Handout\HandoutContentValidator;
use PHPUnit\Framework\TestCase;

final class HandoutContentValidatorTest extends TestCase
{
    public function testNormalizesOnlySupportedTiptapJson(): void
    {
        $result = (new HandoutContentValidator())->validate([
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                    ['type' => 'text', 'text' => 'The clue', 'marks' => [['type' => 'bold']]],
                ]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'image', 'attrs' => ['assetId' => 7, 'alt' => 'Map', 'src' => 'ignored']],
                    ['type' => 'mention', 'attrs' => ['targetType' => 'scene', 'targetId' => 3, 'label' => 'Vault']],
                ]],
                ['type' => 'attachment', 'attrs' => ['assetId' => 8, 'name' => 'Evidence.pdf']],
            ],
        ]);

        self::assertTrue($result['valid']);
        self::assertSame([7, 8], $result['assetIds']);
        self::assertSame([['targetType' => 'scene', 'targetId' => 3, 'label' => 'Vault']], $result['mentions']);
        self::assertSame(['assetId' => 7, 'alt' => 'Map'], $result['data']['content'][1]['content'][0]['attrs']);
        self::assertIsString($result['json']);
        self::assertSame($result['data'], json_decode($result['json'], true));
    }

    public function testRejectsInsecureLinkAndMentionsInPrivateLibrary(): void
    {
        $validator = new HandoutContentValidator();
        $insecure = $validator->validate([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'click',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => 'javascript:alert(1)']]],
                ]],
            ]],
        ]);
        $libraryMention = $validator->validate([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'mention',
                    'attrs' => ['targetType' => 'scene', 'targetId' => 1, 'label' => 'Secret'],
                ]],
            ]],
        ], false);

        self::assertFalse($insecure['valid']);
        self::assertFalse($libraryMention['valid']);
    }

    public function testAcceptsAFreshBlankParagraph(): void
    {
        $result = (new HandoutContentValidator())->validate([
            'type' => 'doc',
            'content' => [['type' => 'paragraph']],
        ], false);

        self::assertTrue($result['valid']);
    }

    public function testRejectsNodesInAnInvalidDocumentPosition(): void
    {
        $result = (new HandoutContentValidator())->validate([
            'type' => 'doc',
            'content' => [[
                'type' => 'mention',
                'attrs' => ['targetType' => 'scene', 'targetId' => 1, 'label' => 'Vault'],
            ]],
        ]);

        self::assertFalse($result['valid']);
    }
}
