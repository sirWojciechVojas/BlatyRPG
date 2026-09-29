<?php

namespace App\Services\Compendium;

final class CompendiumImportRecordValidator
{
    /** @return array<int,string> */
    public function validate($record): array
    {
        if (!is_array($record)) {
            return ['Record must be a JSON object.'];
        }
        $errors = [];
        $id = (string) ($record['id'] ?? '');
        if (!preg_match('/^warhammerpl:[0-9]+$/', $id)) {
            $errors[] = 'id must use warhammerpl:<page_id>.';
        }
        $title = trim((string) ($record['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 180) {
            $errors[] = 'title is required and must not exceed 180 characters.';
        }
        foreach (['body_html', 'plain_text', 'wikitext'] as $field) {
            if (!isset($record[$field]) || !is_string($record[$field]) || $record[$field] === '') {
                $errors[] = $field . ' must be non-empty.';
            }
        }
        if (!is_array($record['source'] ?? null)) {
            $errors[] = 'source must be an object.';
        } else {
            $source = $record['source'];
            if ((string) ($source['page_id'] ?? '') !== substr($id, strlen('warhammerpl:'))) {
                $errors[] = 'source.page_id must match id.';
            }
            if (trim((string) ($source['revision_id'] ?? '')) === '') {
                $errors[] = 'source.revision_id is required.';
            }
        }
        $checksum = strtolower((string) ($record['sha256'] ?? ''));
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            $errors[] = 'sha256 must be a lowercase SHA-256 checksum.';
        } elseif (hash('sha256', (string) ($record['wikitext'] ?? '')) !== $checksum) {
            $errors[] = 'sha256 does not match wikitext.';
        }
        foreach (['aliases', 'categories', 'sections', 'links', 'media_references', 'quality_flags'] as $field) {
            if (!is_array($record[$field] ?? null)) {
                $errors[] = $field . ' must be an array.';
            }
        }
        return $errors;
    }
}
