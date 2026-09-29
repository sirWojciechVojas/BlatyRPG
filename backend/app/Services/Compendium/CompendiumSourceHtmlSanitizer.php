<?php

namespace App\Services\Compendium;

/** Server-side allow-list sanitizer for imported wiki fragments. */
final class CompendiumSourceHtmlSanitizer
{
    private const ALLOWED = [
        'a', 'abbr', 'b', 'blockquote', 'br', 'caption', 'code', 'dd', 'del',
        'details', 'div', 'dl', 'dt', 'em', 'figcaption', 'figure', 'h2', 'h3',
        'h4', 'h5', 'h6', 'hr', 'i', 'li', 'mark', 'ol', 'p', 'pre', 'small',
        'span', 'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'th',
        'thead', 'tr', 'u', 'ul',
    ];

    private const DROP_WITH_CONTENT = [
        'applet', 'audio', 'base', 'canvas', 'embed', 'form', 'frame', 'frameset',
        'iframe', 'input', 'link', 'meta', 'object', 'script', 'select', 'source',
        'style', 'svg', 'textarea', 'video',
    ];

    public function sanitize(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $document = $this->document($html);
        $root = $document->getElementById('compendium-source-root');
        if (!$root) {
            return '';
        }
        $this->sanitizeChildren($root);
        return $this->innerHtml($root);
    }

    /** @return array<int,array{id:string,title:string,level:int}> */
    public function sections(string $html, array $declared = []): array
    {
        $sections = [];
        if ($this->hasLead($html)) {
            $sections[] = ['id' => 'lead', 'title' => 'Wprowadzenie', 'level' => 1];
        }
        foreach ($declared as $section) {
            $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($section['id'] ?? ''));
            $title = trim((string) ($section['title'] ?? ''));
            $level = max(2, min(6, (int) ($section['level'] ?? 2) + 1));
            if ($id !== '' && $title !== '') {
                $sections[$id] = ['id' => $id, 'title' => $title, 'level' => $level];
            }
        }
        return array_values($sections);
    }

    public function selectSections(string $html, array $keys): string
    {
        $wanted = array_fill_keys(array_filter(array_map('strval', $keys)), true);
        if (!$wanted) {
            return '';
        }
        $document = $this->document($html);
        $root = $document->getElementById('compendium-source-root');
        if (!$root) {
            return '';
        }
        $output = $document->createElement('div');
        $active = isset($wanted['lead']);
        foreach (iterator_to_array($root->childNodes) as $node) {
            if ($node instanceof \DOMElement && preg_match('/^h([2-6])$/', strtolower($node->tagName))) {
                $active = isset($wanted[$node->getAttribute('id')]);
            }
            if ($active) {
                $output->appendChild($node->cloneNode(true));
            }
        }
        return $this->innerHtml($output);
    }

    private function sanitizeChildren(\DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $parent->removeChild($node);
                continue;
            }
            if (!in_array($tag, self::ALLOWED, true)) {
                $this->sanitizeChildren($node);
                while ($node->firstChild) {
                    $parent->insertBefore($node->firstChild, $node);
                }
                $parent->removeChild($node);
                continue;
            }
            $this->sanitizeElement($node, $tag);
            $this->sanitizeChildren($node);
        }
    }

    private function sanitizeElement(\DOMElement $element, string $tag): void
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            $attributes[] = $attribute->name;
        }
        foreach ($attributes as $name) {
            $lower = strtolower($name);
            $keep = ($lower === 'title')
                || ($lower === 'id' && preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $element->getAttribute($name)))
                || (in_array($lower, ['colspan', 'rowspan'], true) && in_array($tag, ['td', 'th'], true))
                || ($lower === 'open' && $tag === 'details')
                || ($lower === 'href' && $tag === 'a');
            if (!$keep || strpos($lower, 'on') === 0 || $lower === 'style') {
                $element->removeAttribute($name);
            }
        }
        if ($tag !== 'a') {
            return;
        }
        $href = trim(html_entity_decode($element->getAttribute('href'), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (preg_match('~(?:^|/)([0-9]+)\.html(?:#([^?#]+))?$~', $href, $match)) {
            $element->setAttribute('href', '#');
            $element->setAttribute('data-compendium-source-id', 'warhammerpl:' . $match[1]);
            if (!empty($match[2])) {
                $element->setAttribute('data-compendium-anchor', rawurldecode($match[2]));
            }
            return;
        }
        if (preg_match('~^https?://~i', $href)) {
            $element->setAttribute('href', $href);
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
            return;
        }
        if (preg_match('/^#[a-zA-Z][a-zA-Z0-9_-]{0,79}$/', $href)) {
            $element->setAttribute('href', $href);
            return;
        }
        $element->removeAttribute('href');
    }

    private function document(string $html): \DOMDocument
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="compendium-source-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        return $document;
    }

    private function innerHtml(\DOMNode $node): string
    {
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }
        return $html;
    }

    private function hasLead(string $html): bool
    {
        $beforeHeading = preg_split('/<h[2-6]\b/i', $html, 2)[0] ?? '';
        return trim(html_entity_decode(strip_tags($beforeHeading), ENT_QUOTES | ENT_HTML5, 'UTF-8')) !== '';
    }
}
