<?php

namespace App\Services\Compendium;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Imports the user-supplied Polish WFRP2 core-book corpus and Bestiary. */
final class CompendiumWfrp2CorebookImporter
{
    private const EMPTY_DOCUMENT = '{"type":"doc","content":[{"type":"paragraph","content":[]}]}';
    private const UUID_NAMESPACE = '0eb9d64e-f12b-5fb4-b24a-33f21ecdf701';
    private const REQUIRED_STAT_KEYS = [
        'ww', 'us', 'k', 'odp', 'zr', 'int', 'sw', 'ogd',
        'a', 'zyw', 's', 'wt', 'sz', 'mag', 'po', 'pp',
    ];
    private const BUILTIN_TYPES = [
        ['general', 'General', 'book'], ['place', 'Place', 'map'],
        ['person', 'Person', 'user'], ['faction', 'Faction', 'users'],
        ['event', 'Event', 'calendar'], ['history', 'Historical period', 'history'],
        ['creature', 'Creature / NPC', 'paw'],
    ];
    private const IMPERIAL_MONTHS = [
        ['name' => 'Powiedźmie', 'days' => 32],
        ['name' => 'Zmiana Roku', 'days' => 33],
        ['name' => 'Czas Orki', 'days' => 33],
        ['name' => 'Czas Sigmara', 'days' => 33],
        ['name' => 'Czas Lata', 'days' => 33],
        ['name' => 'Przed Tajemnicą', 'days' => 33],
        ['name' => 'Po Tajemnicy', 'days' => 32],
        ['name' => 'Czas Zbiorów', 'days' => 33],
        ['name' => 'Czas Warzenia', 'days' => 33],
        ['name' => 'Czas Mrozów', 'days' => 33],
        ['name' => 'Czas Ulryka', 'days' => 33],
        ['name' => 'Przedwiedźmie', 'days' => 33],
    ];

    private $db;
    private $sanitizer;
    private $worldId;
    private $systemId;
    private $sourceId;
    private $userId;
    private $runId;
    private $typeIds = [];
    private $categoryIds = [];
    private $calendar = null;

    public function __construct(
        ?BaseConnection $db = null,
        ?CompendiumSourceHtmlSanitizer $sanitizer = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->sanitizer = $sanitizer ?: new CompendiumSourceHtmlSanitizer();
    }

    /** @return array{valid:bool,records:int,bestiaryEntries:int,errors:array<int,string>,checksum:string} */
    public function validate(string $corpusPath): array
    {
        $real = realpath($corpusPath);
        if ($real === false || !is_file($real)) {
            return [
                'valid' => false, 'records' => 0, 'bestiaryEntries' => 0,
                'errors' => ['Core-book corpus was not found: ' . $corpusPath],
                'checksum' => '',
            ];
        }
        $checksum = hash_file('sha256', $real) ?: '';
        try {
            $corpus = $this->load($real);
        } catch (\Throwable $error) {
            return [
                'valid' => false, 'records' => 0, 'bestiaryEntries' => 0,
                'errors' => [$error->getMessage()], 'checksum' => $checksum,
            ];
        }
        $errors = [];
        if (($corpus['schemaVersion'] ?? '') !== '1.0.0') {
            $errors[] = 'Unsupported core-book corpus schema version.';
        }
        $source = is_array($corpus['source'] ?? null) ? $corpus['source'] : [];
        if (($source['key'] ?? '') !== 'wfrp2-corebook-pl-reconstruction-269') {
            $errors[] = 'Unexpected core-book source key.';
        }
        if ((int) ($source['pageCount'] ?? 0) !== 269) {
            $errors[] = 'The source must contain all 269 PDF pages.';
        }
        if (!preg_match('/^[a-f0-9]{64}$/', (string) ($source['pdfSha256'] ?? ''))) {
            $errors[] = 'The source PDF checksum is invalid.';
        }
        $records = is_array($corpus['records'] ?? null) ? $corpus['records'] : [];
        if (count($records) !== (int) (($corpus['counts']['records'] ?? -1))) {
            $errors[] = 'The manifest record count does not match the corpus.';
        }
        $seen = [];
        $bestiary = 0;
        $namedNpcs = 0;
        $genericNpcs = 0;
        $scopeCounts = ['rules' => 0, 'world' => 0, 'scenario' => 0, 'bestiary' => 0];
        $professionArchetypes = [
            'Kieszonkowiec', 'Kowal', 'Kramarz', 'Najmita', 'Strażnik miejski',
            'Szuler', 'Pirat', 'Zawadiaka', 'Zbir', 'Zbój', 'Żebrak',
        ];
        foreach ($records as $index => $record) {
            $prefix = 'Record ' . ($index + 1) . ': ';
            if (!is_array($record)) {
                $errors[] = $prefix . 'must be an object.';
                continue;
            }
            $externalId = (string) ($record['id'] ?? '');
            if (!preg_match('/^corebook(?::[a-z0-9-]+){2,4}$/', $externalId)) {
                $errors[] = $prefix . 'invalid source ID.';
            } elseif (isset($seen[$externalId])) {
                $errors[] = $prefix . 'duplicate source ID ' . $externalId . '.';
            }
            $seen[$externalId] = true;
            $title = trim((string) ($record['title'] ?? ''));
            if ($title === '' || mb_strlen($title) > 180) {
                $errors[] = $prefix . 'title is required and must not exceed 180 characters.';
            }
            if (!in_array($record['type'] ?? null, ['concept', 'place', 'person', 'event', 'creature'], true)) {
                $errors[] = $prefix . 'has an unsupported semantic type.';
            }
            if (!in_array($record['entryType'] ?? null, ['general', 'place', 'person', 'event', 'history', 'creature'], true)) {
                $errors[] = $prefix . 'has an unsupported compendium entry type.';
            }
            $scope = (string) ($record['scope'] ?? '');
            if (!array_key_exists($scope, $scopeCounts)) {
                $errors[] = $prefix . 'scope must be rules, world, scenario or bestiary.';
            } else {
                $scopeCounts[$scope]++;
            }
            if (($record['kind'] ?? '') === 'chapter' || array_key_exists('pages', $record)) {
                $errors[] = $prefix . 'must be a curated entry, not a complete chapter.';
            }
            if (!is_array($record['content'] ?? null) || $record['content'] === []) {
                $errors[] = $prefix . 'must contain curated content.';
            }
            if (!preg_match('/^[a-f0-9]{64}$/', (string) ($record['checksum'] ?? ''))) {
                $errors[] = $prefix . 'checksum is invalid.';
            }
            $chronology = $record['chronology'] ?? null;
            if (($record['entryType'] ?? null) === 'history' && !is_array($chronology)) {
                $errors[] = $prefix . 'historical entries require chronology.';
            } elseif (is_array($chronology)) {
                $precision = (string) ($chronology['precision'] ?? '');
                $startYear = (int) ($chronology['start']['year'] ?? 0);
                $endYear = (int) ($chronology['end']['year'] ?? 0);
                if (!in_array($precision, ['year', 'range'], true) || $startYear < 1) {
                    $errors[] = $prefix . 'chronology must contain a positive year and year/range precision.';
                } elseif ($precision === 'range' && $endYear < $startYear) {
                    $errors[] = $prefix . 'chronology range is invalid.';
                }
            }
            $sourcePages = array_map('intval', (array) ($record['source']['pdfPages'] ?? []));
            if (!$sourcePages || min($sourcePages) < 1 || max($sourcePages) > 269) {
                $errors[] = $prefix . 'must cite valid PDF pages.';
            }
            if (($record['kind'] ?? '') === 'named_npc') {
                $namedNpcs++;
                $identity = is_array($record['identity'] ?? null) ? $record['identity'] : [];
                if (trim((string) ($identity['givenName'] ?? '')) === ''
                    || trim((string) ($identity['displayName'] ?? '')) === ''
                    || empty($identity['sourceSuppliedName'])
                    || ($record['npcKind'] ?? '') !== 'named'
                    || ($record['type'] ?? '') !== 'person') {
                    $errors[] = $prefix . 'a named NPC must preserve the name supplied by the source.';
                }
            }
            if (($record['kind'] ?? '') === 'generic_npc') {
                $genericNpcs++;
                $identity = is_array($record['identity'] ?? null) ? $record['identity'] : [];
                if (!in_array($title, $professionArchetypes, true)
                    || ($record['npcKind'] ?? '') !== 'generic'
                    || ($record['type'] ?? '') !== 'person'
                    || empty($identity['archetype'])
                    || !empty($identity['givenName'])
                    || !empty($identity['familyName'])
                    || !empty($identity['sourceSuppliedName'])) {
                    $errors[] = $prefix . 'a generic NPC must be stored as an explicitly unnamed archetype.';
                }
            } elseif (in_array($title, $professionArchetypes, true)) {
                $errors[] = $prefix . 'a profession archetype must be explicitly classified as a generic NPC.';
            }
            if (!empty($record['bestiary'])) {
                $bestiary++;
                $attributes = $record['profile']['attributes'] ?? null;
                if (!is_array($attributes) || array_keys($attributes) !== self::REQUIRED_STAT_KEYS) {
                    $errors[] = $prefix . 'Bestiary profile must contain the complete ordered WFRP2 stat line.';
                }
                $token = $record['profile']['token'] ?? null;
                if (!is_array($token) || (int) ($token['width'] ?? 0) < 1 || (int) ($token['height'] ?? 0) < 1) {
                    $errors[] = $prefix . 'Bestiary token defaults are invalid.';
                }
            }
        }
        if ($bestiary !== (int) (($corpus['counts']['bestiaryEntries'] ?? -1))) {
            $errors[] = 'The manifest Bestiary count does not match the corpus.';
        }
        if ($namedNpcs !== (int) (($corpus['counts']['namedNpcProfiles'] ?? -1))) {
            $errors[] = 'The manifest named NPC count does not match the corpus.';
        }
        if ($genericNpcs !== (int) (($corpus['counts']['genericNpcProfiles'] ?? -1))) {
            $errors[] = 'The manifest generic NPC count does not match the corpus.';
        }
        foreach ([
            'rules' => 'rulesEntries', 'world' => 'worldEntries',
            'scenario' => 'scenarioEntries',
        ] as $scope => $countKey) {
            if ($scopeCounts[$scope] !== (int) (($corpus['counts'][$countKey] ?? -1))) {
                $errors[] = 'The manifest ' . $scope . ' count does not match the corpus.';
            }
        }
        return [
            'valid' => !$errors, 'records' => count($records),
            'bestiaryEntries' => $bestiary,
            'errors' => array_slice($errors, 0, 100), 'checksum' => $checksum,
        ];
    }

    public function import(
        string $corpusPath,
        string $universeCode = 'old_world',
        ?int $userId = null
    ): array {
        $validation = $this->validate($corpusPath);
        if (!$validation['valid']) {
            throw new RuntimeException('Core-book corpus validation failed: ' . implode(' ', $validation['errors']));
        }
        $corpus = $this->load((string) realpath($corpusPath));
        $this->context($universeCode, $userId, (array) $corpus['source']);
        $this->types();
        $this->calendar();
        $records = (array) $corpus['records'];
        $this->runId = $this->createRun(basename($corpusPath), $validation['checksum'], count($records));
        $report = [
            'processed' => 0, 'added' => 0, 'updated' => 0, 'skipped' => 0,
            'removed' => 0, 'errors' => 0, 'bestiaryEntries' => 0, 'errorDetails' => [],
        ];
        $report['removed'] = $this->removeObsoleteRecords(array_map(
            static fn (array $record): string => (string) $record['id'],
            $records
        ));
        foreach ($records as $index => $record) {
            $this->db->transBegin();
            try {
                $action = $this->importRecord((array) $record, (array) $corpus['source'], $index + 1);
                if ($this->db->transStatus() === false) {
                    throw new RuntimeException('Database rejected the core-book record.');
                }
                $this->db->transCommit();
                $report[$action]++;
                if (!empty($record['bestiary'])) {
                    $report['bestiaryEntries']++;
                }
            } catch (\Throwable $error) {
                $this->db->transRollback();
                $report['errors']++;
                if (count($report['errorDetails']) < 100) {
                    $report['errorDetails'][] = [
                        'line' => $index + 1,
                        'id' => (string) ($record['id'] ?? 'unknown'),
                        'message' => $error->getMessage(),
                    ];
                }
                $this->recordError($index + 1, (string) ($record['id'] ?? ''), $error->getMessage());
            }
            $report['processed']++;
            $this->saveProgress($report, $index + 1);
        }
        $now = $this->now();
        $this->db->table('compendium_import_runs')->where('id', $this->runId)->update([
            'status' => $report['errors'] ? 'completed_with_errors' : 'completed',
            'finished_at' => $now, 'report_json' => $this->json($report), 'updated_at' => $now,
        ]);
        return ['runId' => $this->runId, 'validation' => $validation, 'report' => $report];
    }

    private function importRecord(array $record, array $source, int $line): string
    {
        $now = $this->now();
        $externalId = (string) $record['id'];
        $document = $this->db->table('compendium_source_documents')
            ->where('source_id', $this->sourceId)->where('external_id', $externalId)
            ->get()->getRowArray();
        $sourceUri = $this->sourceUri($source, $record);
        if (!$document) {
            $this->db->table('compendium_source_documents')->insert([
                'source_id' => $this->sourceId, 'external_id' => $externalId,
                'source_uri' => $sourceUri, 'current_revision_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $document = ['id' => (int) $this->db->insertID(), 'current_revision_id' => null];
        }
        $entity = $this->db->table('compendium_entities')->where('world_id', $this->worldId)
            ->where('source_document_id', (int) $document['id'])->get()->getRowArray();
        $revision = $this->db->table('compendium_source_revisions')
            ->where('document_id', (int) $document['id'])
            ->where('checksum', (string) $record['checksum'])->get()->getRowArray();
        if ($revision && $entity) {
            if (!empty($record['bestiary'])) {
                $this->upsertMechanicalProfile((int) $entity['id'], $record, $source);
            }
            $this->recordItem($line, $externalId, $entity, $revision, 'skipped', null, null);
            return 'skipped';
        }

        [$plainText, $html, $declaredSections] = $this->content($record);
        $sanitized = $this->sanitizer->sanitize($html);
        $qualityFlags = ['curated_from_source', 'checked_against_pdf'];
        if (!empty($record['bestiary'])) {
            $qualityFlags[] = 'profile_checked_against_pdf';
        }
        if (($record['scope'] ?? '') === 'scenario') {
            $qualityFlags[] = 'scenario_spoiler';
        }
        if (in_array(($record['npcKind'] ?? ''), ['named', 'generic'], true)) {
            $qualityFlags[] = 'npc_' . $record['npcKind'];
        }
        if (!empty($record['source']['errataApplied'])) {
            $qualityFlags[] = 'official_errata_applied';
        }
        $this->db->table('compendium_source_revisions')->insert([
            'document_id' => (int) $document['id'], 'import_run_id' => $this->runId,
            'entry_version_id' => null, 'external_revision_id' => 'sha256:' . $record['checksum'],
            'title' => mb_substr((string) $record['title'], 0, 180),
            'checksum' => (string) $record['checksum'], 'source_timestamp' => null,
            'contributor' => null, 'raw_wikitext' => $plainText,
            'sanitized_html' => $sanitized, 'plain_text' => $plainText,
            'summary' => mb_substr((string) ($record['summary'] ?? ''), 0, 500) ?: null,
            'sections_json' => $this->json($this->sanitizer->sections($sanitized, $declaredSections)),
            'quality_flags_json' => $this->json($qualityFlags),
            'source_payload_json' => $this->json([
                'source' => $source, 'recordSource' => $record['source'] ?? [],
                'category' => $record['category'] ?? null, 'kind' => $record['kind'] ?? null,
                'scope' => $record['scope'] ?? null, 'identity' => $record['identity'] ?? null,
                'npcKind' => $record['npcKind'] ?? null,
                'chronology' => $record['chronology'] ?? null,
            ]),
            'validation_status' => 'validated', 'published_at' => $now, 'created_at' => $now,
        ]);
        $revisionId = (int) $this->db->insertID();
        $previousSourceId = $entity && $entity['current_source_revision_id'] !== null
            ? (int) $entity['current_source_revision_id'] : null;
        $previousEntryVersionId = null;
        $title = mb_substr(trim((string) $record['title']), 0, 180);
        $aliases = array_values(array_unique(array_filter(array_map('strval', (array) ($record['aliases'] ?? [])))));
        $type = (string) ($record['type'] ?? 'concept');
        $scope = (string) ($record['scope'] ?? 'world');
        $slug = $this->slug('wfrp2-corebook-' . substr($externalId, strlen('corebook:')));
        $search = CompendiumSearchNormalizer::normalize(implode(' ', [
            $title, implode(' ', $aliases), (string) ($record['summary'] ?? ''),
            $plainText, (string) ($record['category'] ?? ''), $scope,
            'WFRP2 podręcznik główny',
        ]));
        if (!$entity) {
            $slug = $this->uniqueSlug($slug);
            $this->db->table('compendium_entries')->insert([
                'world_id' => $this->worldId, 'slug' => $slug, 'draft_version_id' => null,
                'published_version_id' => null, 'revision' => 1, 'status' => 'active',
                'created_by_user_id' => $this->userId, 'created_at' => $now,
                'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entryId = (int) $this->db->insertID();
            $this->db->table('compendium_entities')->insert([
                'world_id' => $this->worldId, 'entry_id' => $entryId,
                'source_document_id' => (int) $document['id'],
                'current_source_revision_id' => $revisionId,
                'canonical_id' => 'entity:' . $this->uuidV5(self::UUID_NAMESPACE, $externalId),
                'type_code' => $type, 'slug' => $slug, 'name' => $title,
                'normalized_name' => CompendiumSearchNormalizer::normalize($title),
                'aliases_normalized' => CompendiumSearchNormalizer::normalize(implode(' ', $aliases)) ?: null,
                'search_text_normalized' => $search, 'player_search_normalized' => null,
                'player_description' => null, 'gm_notes' => null, 'visibility' => 'gm',
                'spoiler_level' => $scope === 'scenario' ? 'scenario_secret' : 'gm_reference',
                'editorial_status' => 'curated_and_verified_against_source',
                'verification_status' => 'verified',
                'canon_status' => 'core_rulebook', 'edition' => 'WFRP2',
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entity = ['id' => (int) $this->db->insertID(), 'entry_id' => $entryId];
        } else {
            $entryId = (int) $entity['entry_id'];
            $entry = $this->db->table('compendium_entries')->where('id', $entryId)->get()->getRowArray();
            $previousEntryVersionId = $entry && $entry['published_version_id'] !== null
                ? (int) $entry['published_version_id'] : null;
            $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update([
                'current_source_revision_id' => $revisionId, 'type_code' => $type,
                'name' => $title, 'normalized_name' => CompendiumSearchNormalizer::normalize($title),
                'aliases_normalized' => CompendiumSearchNormalizer::normalize(implode(' ', $aliases)) ?: null,
                'search_text_normalized' => $search,
                'spoiler_level' => $scope === 'scenario' ? 'scenario_secret' : 'gm_reference',
                'editorial_status' => 'curated_and_verified_against_source',
                'verification_status' => 'verified',
                'canon_status' => 'core_rulebook', 'edition' => 'WFRP2',
                'updated_at' => $now, 'deleted_at' => null,
            ]);
            $this->db->table('compendium_entries')->where('id', $entryId)->update([
                'status' => 'active', 'deleted_at' => null, 'updated_at' => $now,
            ]);
        }
        $versionId = $this->createEntryVersion($entryId, $record, $aliases, $plainText);
        $entry = $this->db->table('compendium_entries')->where('id', $entryId)->get()->getRowArray();
        $entryChanges = ['published_version_id' => $versionId, 'updated_at' => $now];
        if (empty($entry['draft_version_id']) || (int) $entry['draft_version_id'] === (int) $previousEntryVersionId) {
            $entryChanges['draft_version_id'] = $versionId;
        }
        $this->db->table('compendium_entries')->set('revision', 'revision + 1', false)
            ->where('id', $entryId)->update($entryChanges);
        $this->db->table('compendium_source_revisions')->where('id', $revisionId)
            ->update(['entry_version_id' => $versionId]);
        $this->db->table('compendium_source_documents')->where('id', (int) $document['id'])->update([
            'source_uri' => $sourceUri, 'current_revision_id' => $revisionId, 'updated_at' => $now,
        ]);
        $this->replaceNames((int) $entity['id'], (int) $document['id'], $title, $aliases);
        $this->replaceCategory((int) $entity['id'], (string) ($record['category'] ?? 'Podręcznik główny'));
        if (!empty($record['bestiary'])) {
            $this->upsertMechanicalProfile((int) $entity['id'], $record, $source);
        }
        $action = $previousSourceId === null ? 'added' : 'updated';
        $this->recordItem(
            $line, $externalId, $entity, ['id' => $revisionId, 'entry_version_id' => $versionId],
            $action, $previousSourceId, $previousEntryVersionId, $versionId
        );
        return $action;
    }

    private function createEntryVersion(int $entryId, array $record, array $aliases, string $plainText): int
    {
        $next = (int) ($this->db->table('compendium_entry_versions')->selectMax('version_number', 'n')
            ->where('entry_id', $entryId)->get()->getRowArray()['n'] ?? 0) + 1;
        $isBestiary = !empty($record['bestiary']);
        $entryType = (string) ($record['entryType'] ?? 'general');
        $statBlocks = $isBestiary ? [$this->statBlock($record)] : [];
        $chronology = $this->chronology($record);
        $now = $this->now();
        $this->db->table('compendium_entry_versions')->insert([
            'entry_id' => $entryId, 'version_number' => $next, 'state' => 'published',
            'type_id' => $this->typeIds[$entryType],
            'parent_entry_id' => null, 'title' => mb_substr((string) $record['title'], 0, 180),
            'aliases_json' => $this->json($aliases),
            'excerpt' => mb_substr((string) ($record['summary'] ?? ''), 0, 500) ?: null,
            'visibility' => 'gm_only', 'public_content_json' => self::EMPTY_DOCUMENT,
            'gm_content_json' => self::EMPTY_DOCUMENT, 'public_fields_json' => '{}',
            'gm_fields_json' => $this->json([
                'sourceId' => $record['id'], 'sourceType' => $record['type'],
                'sourcePages' => $record['source'] ?? [], 'kind' => $record['kind'] ?? null,
                'scope' => $record['scope'] ?? null, 'identity' => $record['identity'] ?? null,
                'npcKind' => $record['npcKind'] ?? null,
            ]),
            'chronology_json' => $chronology['json'],
            'start_ordinal' => $chronology['start'], 'end_ordinal' => $chronology['end'],
            'stat_blocks_json' => $this->json($statBlocks), 'public_search_text' => null,
            'gm_search_text' => mb_substr(implode(' ', [
                $record['title'], implode(' ', $aliases), $record['summary'] ?? '', $plainText,
            ]), 0, 20000),
            'created_by_user_id' => $this->userId, 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    /** @return array{json:?string,start:?int,end:?int} */
    private function chronology(array $record): array
    {
        $value = $record['chronology'] ?? null;
        if (!is_array($value)) {
            return ['json' => null, 'start' => null, 'end' => null];
        }
        if (!$this->calendar) {
            throw new RuntimeException('The Imperial calendar is not initialized.');
        }
        $start = $this->calendarDate((int) ($value['start']['year'] ?? 0));
        $startOrdinal = CompendiumCalendar::ordinal(
            $start,
            $this->calendar['months'],
            $this->calendar['eras']
        );
        if (!$startOrdinal['valid']) {
            throw new RuntimeException('The historical entry start date is invalid.');
        }
        $precision = (string) ($value['precision'] ?? 'year');
        $end = null;
        $endOrdinal = $startOrdinal['ordinal'];
        if ($precision === 'range') {
            $end = $this->calendarDate((int) ($value['end']['year'] ?? 0));
            $resolvedEnd = CompendiumCalendar::ordinal(
                $end,
                $this->calendar['months'],
                $this->calendar['eras']
            );
            if (!$resolvedEnd['valid'] || $resolvedEnd['ordinal'] < $startOrdinal['ordinal']) {
                throw new RuntimeException('The historical entry date range is invalid.');
            }
            $endOrdinal = $resolvedEnd['ordinal'];
        }
        $this->db->table('compendium_calendars')->where('id', $this->calendar['id'])
            ->update(['structure_locked' => 1, 'updated_at' => $this->now()]);
        return [
            'json' => $this->json([
                'calendarId' => $this->calendar['id'], 'precision' => $precision,
                'start' => $start, 'end' => $end,
            ]),
            'start' => (int) $startOrdinal['ordinal'], 'end' => (int) $endOrdinal,
        ];
    }

    /** @return array{eraId:int,year:int,monthId:int,day:int} */
    private function calendarDate(int $year): array
    {
        return [
            'eraId' => (int) $this->calendar['eras'][0]['id'],
            'year' => $year,
            'monthId' => (int) $this->calendar['months'][0]['id'],
            'day' => 1,
        ];
    }

    private function statBlock(array $record): array
    {
        $profile = (array) $record['profile'];
        $attributes = (array) $profile['attributes'];
        $advances = array_fill_keys(self::REQUIRED_STAT_KEYS, 0);
        $page = (array) ($record['source']['printedPages'] ?? []);
        return [
            'systemId' => $this->systemId,
            'data' => [
                'details' => [
                    'name' => $record['title'], 'true_name' => $record['title'],
                    'race' => $profile['race'] ?: $record['title'],
                    'profession' => $profile['career'] ?: null,
                    'history' => $record['summary'],
                    'source' => 'WFRP2, s. ' . implode('–', $page),
                ],
                'attributes' => [
                    'start' => $attributes, 'advances' => $advances, 'actual' => $attributes,
                    'skills' => array_values((array) ($profile['skills'] ?? [])),
                    'talents' => array_values((array) ($profile['talents'] ?? [])),
                ],
                'bestiary' => $profile,
            ],
            'token' => (array) ($profile['token'] ?? []),
            'assetPublicIds' => [],
        ];
    }

    /** @return array{0:string,1:string,2:array<int,array{id:string,title:string,level:int}>} */
    private function content(array $record): array
    {
        $summary = trim((string) ($record['summary'] ?? ''));
        $plainParts = [$summary];
        $htmlParts = ['<p>' . $this->escape($summary) . '</p>'];
        $sections = [];
        foreach ((array) ($record['content'] ?? []) as $index => $section) {
            $title = trim((string) ($section['title'] ?? 'Informacje')) ?: 'Informacje';
            $text = trim((string) ($section['text'] ?? ''));
            if ($text === '') {
                continue;
            }
            $id = 'section-' . ($index + 1);
            $plainParts[] = $title . ":\n" . $text;
            $htmlParts[] = '<h2 id="' . $id . '">' . $this->escape($title) . '</h2><p>'
                . str_replace("\n", '<br>', $this->escape($text)) . '</p>';
            $sections[] = ['id' => $id, 'title' => $title, 'level' => 2];
        }
        if (empty($record['bestiary'])) {
            return [implode("\n\n", $plainParts), implode("\n", $htmlParts), $sections];
        }

        $profile = (array) ($record['profile'] ?? []);
        $attributes = (array) ($profile['attributes'] ?? []);
        $profileLines = ['Cechy: ' . $this->attributeLine($attributes)];
        foreach ([
            'skills' => 'Umiejętności', 'talents' => 'Zdolności',
            'specialRules' => 'Zasady specjalne', 'weapons' => 'Uzbrojenie',
            'equipment' => 'Wyposażenie', 'previousCareers' => 'Poprzednie profesje',
        ] as $key => $label) {
            if (!empty($profile[$key])) {
                $profileLines[] = $label . ': ' . implode(', ', (array) $profile[$key]);
            }
        }
        foreach (['armour' => 'Zbroja', 'armourPoints' => 'Punkty Zbroi', 'career' => 'Profesja', 'race' => 'Rasa'] as $key => $label) {
            if (!empty($profile[$key])) {
                $profileLines[] = $label . ': ' . $profile[$key];
            }
        }
        if (!empty($record['source']['errataApplied'])) {
            $profileLines[] = 'Errata: ' . $record['source']['errataApplied'];
        }
        $plainParts[] = "Profil WFRP2:\n" . implode("\n", $profileLines);
        $rows = '';
        foreach ($attributes as $key => $value) {
            $rows .= '<tr><th>' . $this->escape(mb_strtoupper((string) $key)) . '</th><td>'
                . $this->escape($value === null ? '—' : (string) $value) . '</td></tr>';
        }
        $htmlParts[] = '<h2 id="profile">Profil WFRP2</h2><table><tbody>' . $rows . '</tbody></table>';
        foreach (array_slice($profileLines, 1) as $line) {
            $htmlParts[] = '<p>' . $this->escape($line) . '</p>';
        }
        $sections[] = ['id' => 'profile', 'title' => 'Profil WFRP2', 'level' => 2];
        return [implode("\n\n", $plainParts), implode("\n", $htmlParts), $sections];
    }

    private function upsertMechanicalProfile(int $entityId, array $record, array $source): void
    {
        $key = (string) $record['id'];
        $data = [
            'entity_id' => $entityId, 'system_id' => $this->systemId,
            'kind' => (string) ($record['kind'] ?? 'creature'), 'variant' => 'default',
            'status' => 'verified', 'usable' => 1,
            'profile_json' => $this->json((array) $record['profile']),
            'source_json' => $this->json([
                'sourceKey' => $source['key'], 'pdfSha256' => $source['pdfSha256'],
                'pages' => $record['source'], 'recordChecksum' => $record['checksum'],
            ]),
            'linked_resource_type' => null, 'linked_resource_id' => null,
            'updated_at' => $this->now(),
        ];
        $existing = $this->db->table('compendium_mechanical_profiles')->where('profile_key', $key)
            ->get()->getRowArray();
        if ($existing) {
            $this->db->table('compendium_mechanical_profiles')->where('id', (int) $existing['id'])->update($data);
        } else {
            $this->db->table('compendium_mechanical_profiles')->insert($data + [
                'profile_key' => $key, 'created_at' => $this->now(),
            ]);
        }
    }

    private function replaceNames(int $entityId, int $documentId, string $title, array $aliases): void
    {
        $this->db->table('compendium_entity_names')->where('entity_id', $entityId)->delete();
        $now = $this->now();
        foreach ([['primary', $title], ...array_map(static fn (string $alias): array => ['alias', $alias], $aliases)] as [$kind, $name]) {
            $this->db->table('compendium_entity_names')->ignore(true)->insert([
                'entity_id' => $entityId, 'locale' => 'pl', 'kind' => $kind,
                'name' => mb_substr($name, 0, 180),
                'normalized_name' => CompendiumSearchNormalizer::normalize($name),
                'source_document_id' => $documentId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function replaceCategory(int $entityId, string $name): void
    {
        $normalized = CompendiumSearchNormalizer::normalize($name);
        if (!isset($this->categoryIds[$normalized])) {
            $row = $this->db->table('compendium_source_categories')->where('source_id', $this->sourceId)
                ->where('normalized_name', $normalized)->get()->getRowArray();
            if (!$row) {
                $this->db->table('compendium_source_categories')->insert([
                    'source_id' => $this->sourceId, 'name' => mb_substr($name, 0, 180),
                    'normalized_name' => $normalized, 'source_count' => 0,
                    'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
                $this->categoryIds[$normalized] = (int) $this->db->insertID();
            } else {
                $this->categoryIds[$normalized] = (int) $row['id'];
            }
        }
        $this->db->table('compendium_entity_categories')->where('entity_id', $entityId)->delete();
        $this->db->table('compendium_entity_categories')->insert([
            'entity_id' => $entityId, 'category_id' => $this->categoryIds[$normalized],
        ]);
    }

    private function context(string $universeCode, ?int $userId, array $source): void
    {
        $system = $this->db->table('rpg_systems')->where('code', 'wfrp2ed')->get()->getRowArray();
        $universe = $this->db->table('rpg_universes')->where('code', $universeCode)->get()->getRowArray();
        if (!$system || !$universe) {
            throw new RuntimeException('wfrp2ed or the selected universe was not found.');
        }
        $world = $this->db->table('compendium_worlds')->where('universe_id', (int) $universe['id'])
            ->get()->getRowArray();
        if (!$world) {
            $now = $this->now();
            $this->db->table('compendium_worlds')->insert([
                'universe_id' => (int) $universe['id'], 'owner_user_id' => null,
                'storage_limit_bytes' => 524288000, 'revision' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $world = ['id' => (int) $this->db->insertID()];
        }
        $user = $userId ? $this->db->table('users')->where('id', $userId)
            ->where('deleted_at', null)->get()->getRowArray() : null;
        if (!$user) {
            $user = $this->db->table('users')->where('role', 'admin')->where('deleted_at', null)
                ->orderBy('id')->get()->getRowArray();
        }
        if (!$user) {
            $user = $this->db->table('users')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        }
        if (!$user) {
            throw new RuntimeException('No active user is available as the core-book import author.');
        }
        $this->worldId = (int) $world['id'];
        $this->systemId = (int) $system['id'];
        $this->userId = (int) $user['id'];
        $existing = $this->db->table('compendium_sources')->where('source_key', $source['key'])->get()->getRowArray();
        $data = [
            'kind' => 'user_supplied_corebook', 'name' => mb_substr((string) $source['name'], 0, 180),
            'language' => 'pl', 'edition' => 'WFRP2', 'base_url' => null,
            'license_status' => (string) $source['licenseStatus'],
            'attribution_status' => (string) $source['attributionStatus'],
            'metadata_json' => $this->json([
                'systemCode' => 'wfrp2ed', 'pdfFilename' => $source['pdfFilename'],
                'pdfSha256' => $source['pdfSha256'], 'pageCount' => $source['pageCount'],
            ]),
            'updated_at' => $this->now(),
        ];
        if ($existing) {
            $this->sourceId = (int) $existing['id'];
            $this->db->table('compendium_sources')->where('id', $this->sourceId)->update($data);
        } else {
            $this->db->table('compendium_sources')->insert($data + [
                'source_key' => $source['key'], 'created_at' => $this->now(),
            ]);
            $this->sourceId = (int) $this->db->insertID();
        }
    }

    private function types(): void
    {
        $now = $this->now();
        foreach (self::BUILTIN_TYPES as $order => [$code, $name, $icon]) {
            $row = $this->db->table('compendium_entry_types')->where('world_id', $this->worldId)
                ->where('code', $code)->get()->getRowArray();
            if (!$row) {
                $this->db->table('compendium_entry_types')->insert([
                    'world_id' => $this->worldId, 'code' => $code, 'name' => $name,
                    'icon' => $icon, 'is_builtin' => 1, 'field_schema_json' => '[]',
                    'sort_order' => $order, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->typeIds[$code] = (int) $this->db->insertID();
            } else {
                $this->typeIds[$code] = (int) $row['id'];
            }
        }
    }

    private function calendar(): void
    {
        $now = $this->now();
        $row = $this->db->table('compendium_calendars')->where('world_id', $this->worldId)
            ->get()->getRowArray();
        if (!$row) {
            $this->db->table('compendium_calendars')->insert([
                'world_id' => $this->worldId, 'name' => 'Kalendarz Imperialny',
                'structure_locked' => 0, 'revision' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $row = ['id' => (int) $this->db->insertID(), 'name' => 'Kalendarz Imperialny'];
        }
        $calendarId = (int) $row['id'];
        $months = $this->db->table('compendium_calendar_months')
            ->where('calendar_id', $calendarId)->orderBy('sort_order')->get()->getResultArray();
        $eras = $this->db->table('compendium_calendar_eras')
            ->where('calendar_id', $calendarId)->orderBy('sort_order')->get()->getResultArray();
        if (!$months) {
            foreach (self::IMPERIAL_MONTHS as $order => $month) {
                $this->db->table('compendium_calendar_months')->insert($month + [
                    'calendar_id' => $calendarId, 'sort_order' => $order,
                ]);
            }
            $months = $this->db->table('compendium_calendar_months')
                ->where('calendar_id', $calendarId)->orderBy('sort_order')->get()->getResultArray();
        }
        if (!$eras) {
            $this->db->table('compendium_calendar_eras')->insert([
                'calendar_id' => $calendarId, 'name' => 'Kalendarz Imperialny',
                'abbreviation' => 'KI', 'epoch_ordinal' => 0,
                'direction' => 1, 'sort_order' => 0,
            ]);
            $eras = $this->db->table('compendium_calendar_eras')
                ->where('calendar_id', $calendarId)->orderBy('sort_order')->get()->getResultArray();
        }
        if (($row['name'] ?? '') === 'Calendar') {
            $this->db->table('compendium_calendars')->where('id', $calendarId)->update([
                'name' => 'Kalendarz Imperialny', 'updated_at' => $now,
            ]);
        }
        $this->calendar = ['id' => $calendarId, 'months' => $months, 'eras' => $eras];
    }

    private function createRun(string $name, string $checksum, int $expected): int
    {
        $now = $this->now();
        $this->db->table('compendium_import_runs')->insert([
            'world_id' => $this->worldId, 'source_id' => $this->sourceId,
            'pack_name' => mb_substr($name, 0, 255), 'pack_sha256' => $checksum,
            'mode' => 'publish', 'status' => 'running', 'checkpoint_line' => 0,
            'expected_records' => $expected, 'processed_records' => 0,
            'added_records' => 0, 'updated_records' => 0, 'skipped_records' => 0,
            'error_records' => 0, 'report_json' => '{}', 'started_at' => $now,
            'finished_at' => null, 'rolled_back_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function saveProgress(array $report, int $line): void
    {
        $this->db->table('compendium_import_runs')->where('id', $this->runId)->update([
            'checkpoint_line' => $line, 'processed_records' => $report['processed'],
            'added_records' => $report['added'], 'updated_records' => $report['updated'],
            'skipped_records' => $report['skipped'], 'error_records' => $report['errors'],
            'report_json' => $this->json($report), 'updated_at' => $this->now(),
        ]);
    }

    private function recordItem(
        int $line,
        string $externalId,
        ?array $entity,
        ?array $revision,
        string $action,
        ?int $previousSource,
        ?int $previousEntry,
        ?int $entryVersion = null
    ): void {
        $now = $this->now();
        $this->db->table('compendium_import_items')->ignore(true)->insert([
            'import_run_id' => $this->runId, 'line_number' => $line,
            'external_id' => $externalId, 'entity_id' => $entity['id'] ?? null,
            'source_revision_id' => $revision['id'] ?? null,
            'previous_source_revision_id' => $previousSource,
            'entry_version_id' => $entryVersion ?: ($revision['entry_version_id'] ?? null),
            'previous_entry_version_id' => $previousEntry, 'action' => $action,
            'status' => 'ok', 'error_message' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function recordError(int $line, string $externalId, string $message): void
    {
        $now = $this->now();
        $this->db->table('compendium_import_items')->ignore(true)->insert([
            'import_run_id' => $this->runId, 'line_number' => $line,
            'external_id' => $externalId ?: 'line:' . $line, 'entity_id' => null,
            'source_revision_id' => null, 'previous_source_revision_id' => null,
            'entry_version_id' => null, 'previous_entry_version_id' => null,
            'action' => 'error', 'status' => 'error',
            'error_message' => mb_substr($message, 0, 65000),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    /** Removes source-owned entries that are no longer part of the curated corpus. */
    private function removeObsoleteRecords(array $currentExternalIds): int
    {
        $documents = $this->db->table('compendium_source_documents document')
            ->select('document.id, document.external_id, entity.id AS entity_id, entity.entry_id')
            ->join(
                'compendium_entities entity',
                'entity.source_document_id = document.id AND entity.world_id = ' . (int) $this->worldId,
                'left'
            )
            ->where('document.source_id', $this->sourceId)
            ->whereNotIn('document.external_id', $currentExternalIds)
            ->get()->getResultArray();
        if (!$documents) {
            return 0;
        }
        $documentIds = [];
        $entryIds = [];
        foreach ($documents as $document) {
            $documentIds[] = (int) $document['id'];
            if ((int) ($document['entry_id'] ?? 0) > 0) {
                $entryIds[] = (int) $document['entry_id'];
            }
        }
        $this->db->transBegin();
        try {
            if ($documentIds) {
                $this->db->table('compendium_source_documents')->whereIn('id', $documentIds)->delete();
            }
            if ($entryIds) {
                $this->db->table('compendium_entries')->whereIn('id', array_values(array_unique($entryIds)))->delete();
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Database rejected removal of obsolete core-book records.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return count($documents);
    }

    private function sourceUri(array $source, array $record): string
    {
        $pages = array_map('intval', (array) ($record['source']['pdfPages'] ?? []));
        $page = $pages ? min($pages) : 1;
        return 'docs/' . basename((string) $source['pdfFilename']) . '#page=' . $page;
    }

    private function attributeLine(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $key => $value) {
            $parts[] = mb_strtoupper((string) $key) . ' ' . ($value === null ? '—' : $value);
        }
        return implode(', ', $parts);
    }

    private function load(string $path): array
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            throw new RuntimeException('Core-book corpus is empty or unreadable.');
        }
        $decoded = json_decode($contents, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Core-book corpus contains invalid JSON.');
        }
        return $decoded;
    }

    private function uniqueSlug(string $base): string
    {
        $base = mb_substr($base ?: 'entry', 0, 170);
        $slug = $base;
        $suffix = 2;
        while ($this->db->table('compendium_entries')->where('world_id', $this->worldId)
            ->where('slug', $slug)->countAllResults()) {
            $slug = mb_substr($base, 0, 165) . '-' . $suffix++;
        }
        return $slug;
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii)), '-') ?: 'entry';
    }

    private function uuidV5(string $namespace, string $name): string
    {
        $hash = sha1(hex2bin(str_replace('-', '', $namespace)) . $name);
        return sprintf('%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8), substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function json($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Core-book data could not be encoded as JSON.');
        }
        return $json;
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
