<?php

use App\Services\Compendium\CompendiumDocumentValidator;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CompendiumDocumentValidatorTest extends CIUnitTestCase
{
    public function testExtractsSearchTextAssetsAndStableMentions(): void
    {
        $result = (new CompendiumDocumentValidator())->validate([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [
                    ['type' => 'text', 'text' => 'Hidden temple'],
                    ['type' => 'compendiumMention', 'attrs' => ['entryId' => 42]],
                    ['type' => 'image', 'attrs' => ['assetId' => 7, 'alt' => 'Map']],
                ],
            ]],
        ]);

        $this->assertTrue($result['valid']);
        $this->assertSame('Hidden temple', $result['text']);
        $this->assertSame([7], $result['assetIds']);
        $this->assertSame([42], $result['mentionIds']);
        $this->assertStringNotContainsString('label', $result['json']);
    }

    public function testRejectsInlineDataAndUnsupportedNodes(): void
    {
        $validator = new CompendiumDocumentValidator();
        $this->assertFalse($validator->validate([
            'type' => 'doc', 'content' => [['type' => 'image', 'attrs' => ['src' => 'data:image/png;base64,AAAA']]],
        ])['valid']);
        $this->assertFalse($validator->validate([
            'type' => 'doc', 'content' => [['type' => 'script', 'content' => []]],
        ])['valid']);
        $this->assertFalse($validator->validate([
            'type' => 'doc', 'content' => [[
                'type' => 'paragraph', 'content' => [[
                    'type' => 'mention',
                    'attrs' => ['targetType' => 'item', 'targetId' => 7, 'label' => 'Secret'],
                ]],
            ]],
        ])['valid']);
    }
}
