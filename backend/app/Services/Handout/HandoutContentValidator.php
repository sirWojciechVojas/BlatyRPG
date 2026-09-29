<?php

namespace App\Services\Handout;

/** Validates the deliberately small Tiptap/ProseMirror document subset. */
final class HandoutContentValidator
{
    private const MAX_BYTES = 153600;
    private const MAX_NODES = 4000;

    private $nodes = 0;
    private $assets = [];
    private $mentions = [];
    private $text = [];
    private $errors = [];
    private $allowMentions = true;

    public function validate($value, bool $allowMentions = true): array
    {
        $this->nodes = 0;
        $this->assets = [];
        $this->mentions = [];
        $this->text = [];
        $this->errors = [];
        $this->allowMentions = $allowMentions;
        if (!is_array($value)) {
            return $this->invalid('content', 'Content must be a document object.');
        }
        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || strlen($encoded) > self::MAX_BYTES) {
            return $this->invalid('content', 'Content is too large.');
        }
        $document = $this->node($value, 'doc', 0);
        if ($this->errors || !$document) {
            return ['valid' => false, 'errors' => $this->errors ?: ['content' => 'Content is invalid.']];
        }
        $normalizedJson = json_encode($document, JSON_UNESCAPED_UNICODE);
        if (!is_string($normalizedJson)) {
            return $this->invalid('content', 'Content could not be encoded.');
        }
        return [
            'valid' => true,
            'data' => $document,
            'json' => $normalizedJson,
            'assetIds' => array_values(array_unique($this->assets)),
            'mentions' => array_values($this->mentions),
            'searchText' => mb_substr(trim(preg_replace('/\s+/u', ' ', implode(' ', $this->text))), 0, 20000),
        ];
    }

    private function invalid(string $field, string $message): array
    {
        return ['valid' => false, 'errors' => [$field => $message]];
    }

    private function node(array $node, ?string $requiredType, int $depth): ?array
    {
        if (++$this->nodes > self::MAX_NODES || $depth > 30) {
            $this->error('content', 'Content is too complex.');
            return null;
        }
        $type = isset($node['type']) ? (string) $node['type'] : '';
        if ($type === '' || ($requiredType !== null && $type !== $requiredType)) {
            $this->error('content', 'Document node type is invalid.');
            return null;
        }
        $allowed = ['doc', 'paragraph', 'heading', 'bulletList', 'orderedList', 'listItem', 'blockquote', 'text', 'hardBreak', 'image', 'attachment', 'mention'];
        if (!in_array($type, $allowed, true)) {
            $this->error('content', 'Document contains an unsupported node.');
            return null;
        }
        $normalized = ['type' => $type];
        if ($type === 'text') {
            $text = isset($node['text']) ? (string) $node['text'] : '';
            if ($text === '' || mb_strlen($text) > 10000) {
                $this->error('content', 'Text node is invalid.');
                return null;
            }
            $this->text[] = $text;
            $normalized['text'] = $text;
            $marks = $this->marks($node['marks'] ?? []);
            if ($marks) $normalized['marks'] = $marks;
            return $normalized;
        }
        if ($type === 'hardBreak') return $normalized;
        if ($type === 'image') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $assetId = filter_var($attrs['assetId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($assetId === false) {
                $this->error('content', 'Image asset is invalid.');
                return null;
            }
            $alt = mb_substr(trim((string) ($attrs['alt'] ?? '')), 0, 200);
            $this->assets[] = (int) $assetId;
            $normalized['attrs'] = ['assetId' => (int) $assetId, 'alt' => $alt];
            return $normalized;
        }
        if ($type === 'attachment') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $assetId = filter_var($attrs['assetId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $name = mb_substr(trim((string) ($attrs['name'] ?? '')), 0, 255);
            if ($assetId === false || $name === '') {
                $this->error('content', 'Attachment is invalid.');
                return null;
            }
            $this->assets[] = (int) $assetId;
            $this->text[] = $name;
            $normalized['attrs'] = ['assetId' => (int) $assetId, 'name' => $name];
            return $normalized;
        }
        if ($type === 'mention') {
            if (!$this->allowMentions) {
                $this->error('content', 'Campaign mentions belong to the published copy.');
                return null;
            }
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $targetType = strtolower(trim((string) ($attrs['targetType'] ?? '')));
            $targetId = filter_var($attrs['targetId'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $label = mb_substr(trim((string) ($attrs['label'] ?? '')), 0, 180);
            if (!in_array($targetType, ['scene', 'character', 'item'], true) || $targetId === false || $label === '') {
                $this->error('content', 'Mention is invalid.');
                return null;
            }
            $key = $targetType . ':' . $targetId;
            $this->mentions[$key] = ['targetType' => $targetType, 'targetId' => (int) $targetId, 'label' => $label];
            $this->text[] = $label;
            $normalized['attrs'] = ['targetType' => $targetType, 'targetId' => (int) $targetId, 'label' => $label];
            return $normalized;
        }
        $attrs = $this->containerAttributes($type, $node['attrs'] ?? []);
        if ($attrs === null) return null;
        if ($attrs) $normalized['attrs'] = $attrs;
        $content = $node['content'] ?? [];
        if (!is_array($content)) {
            $this->error('content', 'Container node content is invalid.');
            return null;
        }
        $children = [];
        foreach ($content as $child) {
            if (!is_array($child)) {
                $this->error('content', 'Document child is invalid.');
                return null;
            }
            if (!$this->allowsChild($type, (string) ($child['type'] ?? ''))) {
                $this->error('content', 'Document structure is invalid.');
                return null;
            }
            $parsed = $this->node($child, null, $depth + 1);
            if (!$parsed) return null;
            $children[] = $parsed;
        }
        if ($type === 'doc' || $type === 'listItem' || $type === 'paragraph') {
            // Tiptap represents a new blank line as an empty paragraph.
            $normalized['content'] = $children;
        } elseif (!$children) {
            $this->error('content', 'Document container is empty.');
            return null;
        } else {
            $normalized['content'] = $children;
        }
        return $normalized;
    }

    private function allowsChild(string $parent, string $child): bool
    {
        $allowed = [
            'doc' => ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'image', 'attachment'],
            // Image is accepted inline as well for older TipTap documents created
            // before the editor's image extension was configured as a block node.
            'paragraph' => ['text', 'hardBreak', 'image', 'mention'],
            'heading' => ['text', 'hardBreak', 'mention'],
            'bulletList' => ['listItem'],
            'orderedList' => ['listItem'],
            'listItem' => ['paragraph', 'bulletList', 'orderedList', 'blockquote', 'image', 'attachment'],
            'blockquote' => ['paragraph', 'heading', 'bulletList', 'orderedList', 'image', 'attachment'],
        ];
        return in_array($child, $allowed[$parent] ?? [], true);
    }

    private function containerAttributes(string $type, $attrs): ?array
    {
        if (!is_array($attrs)) {
            $this->error('content', 'Node attributes are invalid.');
            return null;
        }
        if ($type !== 'heading') return [];
        $level = filter_var($attrs['level'] ?? 1, FILTER_VALIDATE_INT);
        if ($level === false || $level < 1 || $level > 4) {
            $this->error('content', 'Heading level is invalid.');
            return null;
        }
        return ['level' => (int) $level];
    }

    private function marks($marks): array
    {
        if (!is_array($marks)) {
            $this->error('content', 'Text marks are invalid.');
            return [];
        }
        $normalized = [];
        foreach ($marks as $mark) {
            if (!is_array($mark)) {
                $this->error('content', 'Text mark is invalid.');
                return [];
            }
            $type = (string) ($mark['type'] ?? '');
            if (in_array($type, ['bold', 'italic', 'strike'], true)) {
                $normalized[] = ['type' => $type];
                continue;
            }
            if ($type === 'link') {
                $href = trim((string) (($mark['attrs'] ?? [])['href'] ?? ''));
                if (!preg_match('#^https://[^\s]+$#iu', $href) || mb_strlen($href) > 2048) {
                    $this->error('content', 'Link URL is invalid.');
                    return [];
                }
                $normalized[] = ['type' => 'link', 'attrs' => ['href' => $href]];
                continue;
            }
            $this->error('content', 'Text mark is unsupported.');
            return [];
        }
        return $normalized;
    }

    private function error(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) $this->errors[$field] = $message;
    }
}
