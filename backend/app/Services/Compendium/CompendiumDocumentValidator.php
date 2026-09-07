<?php

namespace App\Services\Compendium;

use App\Services\Handout\HandoutContentValidator;

/** Reuses the Handout TipTap contract while storing only stable Compendium IDs. */
final class CompendiumDocumentValidator
{
    private $documents;

    public function __construct(?HandoutContentValidator $documents = null)
    {
        $this->documents = $documents ?: new HandoutContentValidator();
    }

    public function validate($value): array
    {
        $validMentions = true;
        $compatible = $this->toHandoutDocument($value, $validMentions);
        if (!$validMentions || !is_array($compatible)) {
            return ['valid' => false, 'errors' => ['content' => 'Compendium mention is invalid.']];
        }
        $result = $this->documents->validate($compatible, true);
        if (empty($result['valid'])) return $result;

        $document = $this->toCompendiumDocument($result['data']);
        $json = json_encode($document, JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return ['valid' => false, 'errors' => ['content' => 'Content could not be encoded.']];
        }
        return [
            'valid' => true,
            'data' => $document,
            'json' => $json,
            'text' => mb_substr(trim(preg_replace('/\s+/u', ' ', $this->plainText($document))), 0, 20000),
            'assetIds' => array_values(array_unique(array_map('intval', $result['assetIds'] ?? []))),
            'mentionIds' => array_values(array_unique(array_map(
                static fn (array $mention): int => (int) ($mention['targetId'] ?? 0),
                $result['mentions'] ?? []
            ))),
        ];
    }

    private function toHandoutDocument($value, bool &$valid)
    {
        if (!is_array($value)) return $value;
        $copy = $value;
        $type = (string) ($copy['type'] ?? '');
        if ($type === 'mention') {
            $valid = false;
            return $copy;
        }
        if ($type === 'compendiumMention') {
            $entryId = filter_var(($copy['attrs'] ?? [])['entryId'] ?? null, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($entryId === false) {
                $valid = false;
                return $copy;
            }
            return ['type' => 'mention', 'attrs' => [
                'targetType' => 'item', 'targetId' => (int) $entryId, 'label' => '@',
            ]];
        }
        if (isset($copy['content']) && is_array($copy['content'])) {
            $copy['content'] = array_map(function ($child) use (&$valid) {
                return $this->toHandoutDocument($child, $valid);
            }, $copy['content']);
        }
        return $copy;
    }

    private function toCompendiumDocument(array $node): array
    {
        if (($node['type'] ?? '') === 'mention') {
            return ['type' => 'compendiumMention', 'attrs' => [
                'entryId' => (int) (($node['attrs'] ?? [])['targetId'] ?? 0),
            ]];
        }
        if (isset($node['content']) && is_array($node['content'])) {
            $node['content'] = array_map(fn (array $child): array => $this->toCompendiumDocument($child), $node['content']);
        }
        return $node;
    }

    private function plainText(array $node): string
    {
        if (($node['type'] ?? '') === 'text') return (string) ($node['text'] ?? '');
        if (($node['type'] ?? '') === 'attachment') return (string) (($node['attrs'] ?? [])['name'] ?? '');
        if (!is_array($node['content'] ?? null)) return '';
        return implode(' ', array_map(fn (array $child): string => $this->plainText($child), $node['content']));
    }
}
