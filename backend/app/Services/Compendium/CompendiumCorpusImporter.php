<?php

namespace App\Services\Compendium;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Streaming, resumable importer for the Polish Warhammer wiki corpus.
 *
 * Source revisions are immutable. The normal Compendium entry/version tables
 * receive only a small publication shell so the imported corpus can use the
 * existing stable numeric entry IDs and VTT navigation without duplicating the
 * source document in the editor payload.
 */
final class CompendiumCorpusImporter
{
    private const EMPTY_DOCUMENT = '{"type":"doc","content":[{"type":"paragraph","content":[]}]}';
    private const BUILTIN_TYPES = [
        ['general', 'General', 'book'], ['place', 'Place', 'map'],
        ['person', 'Person', 'user'], ['faction', 'Faction', 'users'],
        ['event', 'Event', 'calendar'], ['history', 'Historical period', 'history'],
        ['creature', 'Creature / NPC', 'paw'],
    ];

    private $db;
    private $validator;
    private $sanitizer;
    private $candidateMap = [];
    private $canonicalMap = [];
    private $typeIds = [];
    private $categoryIds = [];
    private $entityIds = [];
    private $worldId;
    private $sourceId;
    private $systemId;
    private $userId;
    private $runId;

    public function __construct(
        ?BaseConnection $db = null,
        ?CompendiumImportRecordValidator $validator = null,
        ?CompendiumSourceHtmlSanitizer $sanitizer = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->validator = $validator ?: new CompendiumImportRecordValidator();
        $this->sanitizer = $sanitizer ?: new CompendiumSourceHtmlSanitizer();
    }

    public function validatePackage(string $packagePath): array
    {
        $package = new CompendiumImportPackage($packagePath);
        $required = [
            'data/articles.jsonl', 'data/categories.json', 'data/redirects.json',
            'data/links.json', 'data/media.json', 'source/warhammerpl-export.xml',
        ];
        $errors = [];
        foreach ($required as $name) {
            if (!$package->has($name)) {
                $errors[] = 'Missing package entry: ' . $name;
            }
        }
        if ($errors) {
            return ['valid' => false, 'records' => 0, 'errors' => $errors, 'checksum' => $package->checksum()];
        }

        $stream = $package->stream('data/articles.jsonl');
        $line = 0;
        $seen = [];
        while (($raw = fgets($stream)) !== false) {
            $line++;
            $record = json_decode($raw, true);
            if (!is_array($record)) {
                $errors[] = "Line {$line}: invalid JSON.";
                continue;
            }
            foreach ($this->validator->validate($record) as $error) {
                if (count($errors) < 100) {
                    $errors[] = "Line {$line}: {$error}";
                }
            }
            $externalId = (string) ($record['id'] ?? '');
            if (isset($seen[$externalId])) {
                $errors[] = "Line {$line}: duplicate source ID {$externalId}.";
            }
            $seen[$externalId] = true;
        }
        fclose($stream);

        foreach (['data/categories.json', 'data/redirects.json', 'data/links.json', 'data/media.json'] as $name) {
            try {
                $package->json($name);
            } catch (\Throwable $error) {
                $errors[] = $error->getMessage();
            }
        }
        if ($line !== 2294) {
            $errors[] = "Expected 2294 articles, found {$line}.";
        }
        return [
            'valid' => !$errors,
            'records' => $line,
            'errors' => array_slice($errors, 0, 100),
            'checksum' => $package->checksum(),
        ];
    }

    public function import(
        string $packagePath,
        ?string $architecturePath = null,
        string $universeCode = 'old_world',
        ?int $userId = null,
        int $batchSize = 50,
        ?int $resumeRunId = null
    ): array {
        $validation = $this->validatePackage($packagePath);
        if (!$validation['valid']) {
            throw new RuntimeException('Package validation failed: ' . implode(' ', $validation['errors']));
        }
        $package = new CompendiumImportPackage($packagePath);
        $architecture = $architecturePath ? new CompendiumImportPackage($architecturePath) : null;
        $this->prepareContext($universeCode, $userId);
        $this->loadArchitectureMaps($architecture);
        $this->ensureTypes();
        $this->importCategories($package->json('data/categories.json'));

        $report = [
            'expected' => (int) $validation['records'], 'processed' => 0,
            'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0,
            'unresolvedLinks' => 0, 'redirects' => 0, 'assets' => 0,
            'errorDetails' => [],
        ];
        $checkpoint = 0;
        if ($resumeRunId !== null) {
            $run = $this->resumeRun($resumeRunId, $validation['checksum']);
            $this->runId = (int) $run['id'];
            $checkpoint = (int) $run['checkpoint_line'];
            foreach (['processed', 'added', 'updated', 'skipped', 'errors'] as $key) {
                $column = $key === 'errors' ? 'error_records' : $key . '_records';
                $report[$key] = (int) ($run[$column] ?? 0);
            }
        } else {
            $this->runId = $this->createRun(basename($packagePath), $validation['checksum'], (int) $validation['records']);
        }

        $stream = null;
        $line = $checkpoint;
        try {
            $stream = $package->stream('data/articles.jsonl');
            $line = 0;
            $batch = [];
            while (($raw = fgets($stream)) !== false) {
                $line++;
                if ($line <= $checkpoint) {
                    continue;
                }
                $batch[] = ['line' => $line, 'record' => json_decode($raw, true)];
                if (count($batch) >= max(1, min(500, $batchSize))) {
                    $this->importBatch($batch, $report);
                    $this->saveProgress($report, $line);
                    $batch = [];
                }
            }
            fclose($stream);
            $stream = null;
            if ($batch) {
                $this->importBatch($batch, $report);
                $this->saveProgress($report, $line);
            }

            $report['unresolvedLinks'] = $this->resolveWikiLinks();
            $report['redirects'] = $this->importRedirects($package->json('data/redirects.json'));
            $report['assets'] = $this->importAssets($package->json('data/media.json'));
            if ($architecture) {
                $this->importArchitectureExamples($architecture);
            }
            $now = $this->now();
            $this->db->table('compendium_import_runs')->where('id', $this->runId)->update([
                'status' => $report['errors'] ? 'completed_with_errors' : 'completed',
                'checkpoint_line' => $line, 'processed_records' => $report['processed'],
                'added_records' => $report['added'], 'updated_records' => $report['updated'],
                'skipped_records' => $report['skipped'], 'error_records' => $report['errors'],
                'report_json' => $this->json($report), 'finished_at' => $now, 'updated_at' => $now,
            ]);
            return ['runId' => $this->runId, 'validation' => $validation, 'report' => $report];
        } catch (\Throwable $error) {
            if (is_resource($stream)) fclose($stream);
            $report['errors']++;
            $report['errorDetails'][] = ['line' => $line, 'id' => 'fatal', 'message' => $error->getMessage()];
            $this->db->table('compendium_import_runs')->where('id', $this->runId)->update([
                'status' => 'failed', 'error_records' => $report['errors'],
                'report_json' => $this->json($report), 'finished_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            throw $error;
        }
    }

    public function rollback(int $runId): array
    {
        $run = $this->db->table('compendium_import_runs')->where('id', $runId)->get()->getRowArray();
        if (!$run || !in_array($run['status'], ['completed', 'completed_with_errors', 'failed'], true)) {
            throw new RuntimeException('Import run cannot be rolled back.');
        }
        if (($run['mode'] ?? '') !== 'publish') {
            throw new RuntimeException('Only source publication imports can be rolled back with this command.');
        }
        $items = $this->db->table('compendium_import_items')->where('import_run_id', $runId)
            ->whereIn('action', ['added', 'updated'])->orderBy('line_number', 'DESC')->get()->getResultArray();
        $restored = 0;
        $this->db->transBegin();
        try {
            foreach ($items as $item) {
                if (empty($item['entity_id'])) {
                    continue;
                }
                $entity = $this->db->table('compendium_entities')->where('id', (int) $item['entity_id'])->get()->getRowArray();
                if (!$entity || (int) ($entity['current_source_revision_id'] ?? 0) !== (int) ($item['source_revision_id'] ?? 0)) {
                    continue;
                }
                $previousSource = $item['previous_source_revision_id'] === null ? null : (int) $item['previous_source_revision_id'];
                $previousEntry = $item['previous_entry_version_id'] === null ? null : (int) $item['previous_entry_version_id'];
                $entry = !empty($entity['entry_id'])
                    ? $this->db->table('compendium_entries')->where('id', (int) $entity['entry_id'])->get()->getRowArray()
                    : null;
                $importedEntryVersion = $item['entry_version_id'] === null ? null : (int) $item['entry_version_id'];
                $userChangedEntry = $entry && (
                    ((int) ($entry['published_version_id'] ?? 0) !== (int) $importedEntryVersion)
                    || (!empty($entry['draft_version_id']) && (int) $entry['draft_version_id'] !== (int) $importedEntryVersion)
                );
                $entityChanges = [
                    'current_source_revision_id' => $previousSource,
                    'deleted_at' => $previousSource === null ? $this->now() : null,
                    'updated_at' => $this->now(),
                ];
                if ($previousSource === null && $userChangedEntry) $entityChanges['entry_id'] = null;
                $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update($entityChanges);
                $this->db->table('compendium_source_documents')->where('id', (int) $entity['source_document_id'])
                    ->update(['current_revision_id' => $previousSource, 'updated_at' => $this->now()]);
                if ($entry) {
                    $changes = ['updated_at' => $this->now()];
                    if ((int) ($entry['published_version_id'] ?? 0) === (int) $importedEntryVersion) {
                        $changes['published_version_id'] = $previousEntry;
                    }
                    if ((int) ($entry['draft_version_id'] ?? 0) === (int) ($item['entry_version_id'] ?? 0)) {
                        $changes['draft_version_id'] = $previousEntry;
                    }
                    $this->db->table('compendium_entries')->where('id', (int) $entity['entry_id'])->update($changes);
                }
                $restored++;
            }
            $this->db->table('compendium_import_runs')->where('id', $runId)->update([
                'status' => 'rolled_back', 'rolled_back_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Database rejected import rollback.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return ['runId' => $runId, 'restored' => $restored, 'campaignDataPreserved' => true];
    }

    private function prepareContext(string $universeCode, ?int $userId): void
    {
        $universe = $this->db->table('rpg_universes')->where('code', $universeCode)->get()->getRowArray();
        if (!$universe) {
            throw new RuntimeException("RPG universe {$universeCode} was not found.");
        }
        $system = $this->db->table('rpg_systems')->where('code', 'wfrp2ed')->get()->getRowArray();
        if (!$system) {
            throw new RuntimeException('Required RPG system wfrp2ed was not found.');
        }
        if ((int) ($universe['default_system_id'] ?? 0) !== (int) $system['id']) {
            $linked = $this->db->table('rpg_system_universes')->where('system_id', (int) $system['id'])
                ->where('universe_id', (int) $universe['id'])->countAllResults();
            if (!$linked) {
                throw new RuntimeException('The selected universe is not linked to wfrp2ed.');
            }
        }
        $world = $this->db->table('compendium_worlds')->where('universe_id', (int) $universe['id'])->get()->getRowArray();
        if (!$world) {
            $now = $this->now();
            $this->db->table('compendium_worlds')->insert([
                'universe_id' => (int) $universe['id'], 'owner_user_id' => null,
                'storage_limit_bytes' => 524288000, 'revision' => 1,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $world = $this->db->table('compendium_worlds')->where('universe_id', (int) $universe['id'])->get()->getRowArray();
        }
        $user = $userId ? $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray() : null;
        if (!$user) {
            $user = $this->db->table('users')->where('role', 'admin')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        }
        if (!$user) {
            $user = $this->db->table('users')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        }
        if (!$user) {
            throw new RuntimeException('No active user is available as the import author.');
        }
        $this->worldId = (int) $world['id'];
        $this->systemId = (int) $system['id'];
        $this->userId = (int) $user['id'];

        $source = $this->db->table('compendium_sources')->where('source_key', 'warhammerpl')->get()->getRowArray();
        $sourceData = [
            'source_key' => 'warhammerpl', 'kind' => 'wiki_export',
            'name' => 'Warhammer Wiki PL — eksport źródłowy', 'language' => 'pl',
            'edition' => 'mixed', 'base_url' => 'https://warhammer.fandom.com/pl/wiki/',
            'license_status' => 'not_verified_from_export',
            'attribution_status' => 'export_contains_last_revision_only',
            'metadata_json' => $this->json(['systemCode' => 'wfrp2ed', 'verification' => 'unverified_source']),
            'updated_at' => $this->now(),
        ];
        if ($source) {
            $this->sourceId = (int) $source['id'];
            $this->db->table('compendium_sources')->where('id', $this->sourceId)->update($sourceData);
        } else {
            $sourceData['created_at'] = $this->now();
            $this->db->table('compendium_sources')->insert($sourceData);
            $this->sourceId = (int) $this->db->insertID();
        }
    }

    private function loadArchitectureMaps(?CompendiumImportPackage $architecture): void
    {
        if (!$architecture) {
            return;
        }
        if ($architecture->has('audit/entity-candidates.jsonl')) {
            $stream = $architecture->stream('audit/entity-candidates.jsonl');
            while (($line = fgets($stream)) !== false) {
                $row = json_decode($line, true);
                if (is_array($row) && !empty($row['source_record_id'])) {
                    $this->candidateMap[(string) $row['source_record_id']] = [
                        'type' => (string) ($row['candidate_type'] ?? 'lore'),
                        'slug' => (string) ($row['slug_candidate'] ?? ''),
                    ];
                }
            }
            fclose($stream);
        }
        if ($architecture->has('audit/id-map.json')) {
            $map = $architecture->json('audit/id-map.json');
            foreach ((array) ($map['records'] ?? []) as $row) {
                if (!empty($row['legacy_source_id']) && !empty($row['id'])) {
                    $this->canonicalMap[(string) $row['legacy_source_id']] = (string) $row['id'];
                }
            }
        }
    }

    private function ensureTypes(): void
    {
        $now = $this->now();
        foreach (self::BUILTIN_TYPES as $order => $definition) {
            [$code, $name, $icon] = $definition;
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

    private function importCategories(array $categories): void
    {
        $now = $this->now();
        $existing = [];
        foreach ($this->db->table('compendium_source_categories')->select('id,normalized_name')
            ->where('source_id', $this->sourceId)->get()->getResultArray() as $row) {
            $existing[(string) $row['normalized_name']] = (int) $row['id'];
        }
        $newRows = [];
        $updates = [];
        foreach ($categories as $category) {
            $name = trim((string) ($category['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized = CompendiumSearchNormalizer::normalize($name);
            $data = ['name' => mb_substr($name, 0, 180), 'source_count' => (int) ($category['count'] ?? 0), 'updated_at' => $now];
            if (isset($existing[$normalized])) {
                $updates[] = $data + ['id' => $existing[$normalized]];
            } else {
                $newRows[] = $data + [
                    'source_id' => $this->sourceId, 'normalized_name' => $normalized, 'created_at' => $now,
                ];
            }
        }
        if ($newRows) $this->db->table('compendium_source_categories')->insertBatch($newRows, null, 250);
        if ($updates) $this->db->table('compendium_source_categories')->updateBatch($updates, 'id', 250);
        foreach ($this->db->table('compendium_source_categories')->select('id,normalized_name')
            ->where('source_id', $this->sourceId)->get()->getResultArray() as $row) {
            $this->categoryIds[(string) $row['normalized_name']] = (int) $row['id'];
        }
    }

    private function importBatch(array $batch, array &$report): void
    {
        $before = $report;
        $this->db->transBegin();
        try {
            foreach ($batch as $item) {
                $action = $this->importArticle((int) $item['line'], (array) $item['record']);
                $report[$action]++;
                $report['processed']++;
            }
            if ($this->db->transStatus() === false) {
                throw new RuntimeException('Database rejected an article batch.');
            }
            $this->db->transCommit();
        } catch (\Throwable $batchError) {
            $this->db->transRollback();
            $report = $before;
            foreach ($batch as $item) {
                $this->db->transBegin();
                try {
                    $action = $this->importArticle((int) $item['line'], (array) $item['record']);
                    if ($this->db->transStatus() === false) {
                        throw new RuntimeException('Database rejected the article.');
                    }
                    $this->db->transCommit();
                    $report[$action]++;
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    $report['errors']++;
                    if (count($report['errorDetails']) < 100) {
                        $report['errorDetails'][] = [
                            'line' => (int) $item['line'],
                            'id' => (string) (($item['record']['id'] ?? 'unknown')),
                            'message' => $error->getMessage(),
                        ];
                    }
                    $this->recordError((int) $item['line'], (string) ($item['record']['id'] ?? ''), $error->getMessage());
                }
                $report['processed']++;
            }
        }
    }

    private function importArticle(int $line, array $record): string
    {
        $externalId = (string) $record['id'];
        $document = $this->db->table('compendium_source_documents')->where('source_id', $this->sourceId)
            ->where('external_id', $externalId)->get()->getRowArray();
        $now = $this->now();
        if (!$document) {
            $this->db->table('compendium_source_documents')->insert([
                'source_id' => $this->sourceId, 'external_id' => $externalId,
                'source_uri' => $record['source']['url'] ?? null, 'current_revision_id' => null,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $document = $this->db->table('compendium_source_documents')->where('id', (int) $this->db->insertID())->get()->getRowArray();
        }
        $existingRevision = $this->db->table('compendium_source_revisions')
            ->where('document_id', (int) $document['id'])
            ->where('external_revision_id', (string) $record['source']['revision_id'])
            ->where('checksum', (string) $record['sha256'])->get()->getRowArray();
        $entity = $this->db->table('compendium_entities')->where('world_id', $this->worldId)
            ->where('source_document_id', (int) $document['id'])->get()->getRowArray();
        if ($existingRevision) {
            if ($entity) $this->enrichExistingEntity($record, $entity, $existingRevision);
            $this->recordItem($line, $externalId, $entity, $existingRevision, 'skipped', null, null);
            return 'skipped';
        }

        $sanitized = $this->sanitizer->sanitize((string) $record['body_html']);
        $sections = $this->sanitizer->sections($sanitized, (array) $record['sections']);
        $source = (array) $record['source'];
        $timestamp = !empty($source['timestamp']) && strtotime((string) $source['timestamp']) !== false
            ? gmdate('Y-m-d H:i:s', strtotime((string) $source['timestamp'])) : null;
        $this->db->table('compendium_source_revisions')->insert([
            'document_id' => (int) $document['id'], 'import_run_id' => $this->runId,
            'entry_version_id' => null, 'external_revision_id' => (string) $source['revision_id'],
            'title' => mb_substr((string) $record['title'], 0, 180), 'checksum' => (string) $record['sha256'],
            'source_timestamp' => $timestamp, 'contributor' => $this->nullableText($source['last_contributor'] ?? null, 190),
            'raw_wikitext' => (string) $record['wikitext'], 'sanitized_html' => $sanitized,
            'plain_text' => (string) $record['plain_text'], 'summary' => $record['summary'] ?? null,
            'sections_json' => $this->json($sections), 'quality_flags_json' => $this->json($record['quality_flags']),
            'source_payload_json' => $this->json($source), 'validation_status' => 'validated',
            'published_at' => $now, 'created_at' => $now,
        ]);
        $sourceRevisionId = (int) $this->db->insertID();
        $action = $entity ? 'updated' : 'added';
        $previousSourceId = $entity && $entity['current_source_revision_id'] !== null ? (int) $entity['current_source_revision_id'] : null;
        $previousEntryVersionId = null;

        $candidate = $this->candidateMap[$externalId] ?? ['type' => 'lore', 'slug' => ''];
        $sourceType = $this->sourceType((string) $candidate['type']);
        $entryType = $this->entryType($sourceType);
        $title = mb_substr(trim((string) $record['title']), 0, 180);
        $aliases = array_values(array_unique(array_filter(array_map('strval', (array) $record['aliases']))));
        $normalizedAliases = CompendiumSearchNormalizer::normalize(implode(' ', $aliases));
        $categoryText = implode(' ', array_map('strval', (array) $record['categories']));
        $search = CompendiumSearchNormalizer::normalize(implode(' ', [
            $title, implode(' ', $aliases), (string) ($record['summary'] ?? ''),
            (string) $record['plain_text'], $categoryText, 'warhammerpl mixed wfrp2ed',
        ]));
        $slug = trim((string) $candidate['slug']) ?: $this->slug($title . '-' . substr($externalId, 13));
        $canonicalId = $this->canonicalMap[$externalId] ?? ('entity:' . $this->uuidV5('e49f0c55-47bb-4a72-a217-5c8dc328bd4f', $externalId));

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
                'source_document_id' => (int) $document['id'], 'current_source_revision_id' => $sourceRevisionId,
                'canonical_id' => $canonicalId, 'type_code' => $sourceType, 'slug' => $slug,
                'name' => $title, 'normalized_name' => CompendiumSearchNormalizer::normalize($title),
                'aliases_normalized' => $normalizedAliases ?: null, 'search_text_normalized' => $search,
                'player_search_normalized' => null, 'player_description' => null, 'gm_notes' => null,
                'visibility' => 'gm', 'spoiler_level' => 'unreviewed',
                'editorial_status' => (string) ($record['editorial_status'] ?? 'source_preserved_not_proofread'),
                'verification_status' => 'unverified', 'canon_status' => 'unreviewed', 'edition' => 'mixed',
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entityId = (int) $this->db->insertID();
            $entity = ['id' => $entityId, 'entry_id' => $entryId, 'current_source_revision_id' => null];
        } else {
            $entryId = (int) $entity['entry_id'];
            $entry = $this->db->table('compendium_entries')->where('id', $entryId)->get()->getRowArray();
            $previousEntryVersionId = $entry && $entry['published_version_id'] !== null ? (int) $entry['published_version_id'] : null;
            $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update([
                'current_source_revision_id' => $sourceRevisionId, 'type_code' => $sourceType,
                'name' => $title, 'normalized_name' => CompendiumSearchNormalizer::normalize($title),
                'aliases_normalized' => $normalizedAliases ?: null, 'search_text_normalized' => $search,
                'editorial_status' => (string) ($record['editorial_status'] ?? 'source_preserved_not_proofread'),
                'edition' => 'mixed', 'updated_at' => $now, 'deleted_at' => null,
            ]);
        }

        $entryVersionId = $this->createEntryVersion($entryId, $entryType, $title, $aliases, $record, $previousEntryVersionId);
        $entry = $this->db->table('compendium_entries')->where('id', $entryId)->get()->getRowArray();
        $entryChanges = ['published_version_id' => $entryVersionId, 'updated_at' => $now];
        if (empty($entry['draft_version_id']) || (int) $entry['draft_version_id'] === (int) $previousEntryVersionId) {
            $entryChanges['draft_version_id'] = $entryVersionId;
        }
        $this->db->table('compendium_entries')->set('revision', 'revision + 1', false)
            ->where('id', $entryId)->update($entryChanges);
        $this->db->table('compendium_source_revisions')->where('id', $sourceRevisionId)
            ->update(['entry_version_id' => $entryVersionId]);
        $this->db->table('compendium_source_documents')->where('id', (int) $document['id'])->update([
            'source_uri' => $source['url'] ?? $document['source_uri'],
            'current_revision_id' => $sourceRevisionId, 'updated_at' => $now,
        ]);
        $this->replaceNames((int) $entity['id'], (int) $document['id'], $title, $aliases);
        $this->replaceEntityCategories((int) $entity['id'], (array) $record['categories']);
        $this->replaceWikiLinks((int) $entity['id'], $sourceRevisionId, (array) $record['links']);
        $revisionRow = ['id' => $sourceRevisionId];
        $this->recordItem($line, $externalId, $entity, $revisionRow, $action, $previousSourceId, $previousEntryVersionId, $entryVersionId);
        $this->entityIds[$externalId] = (int) $entity['id'];
        return $action;
    }

    private function createEntryVersion(int $entryId, string $type, string $title, array $aliases, array $record, ?int $previous): int
    {
        $next = (int) ($this->db->table('compendium_entry_versions')->selectMax('version_number', 'n')
            ->where('entry_id', $entryId)->get()->getRowArray()['n'] ?? 0) + 1;
        $summary = trim((string) ($record['summary'] ?? ''));
        $gmSearch = mb_substr(implode(' ', [$title, implode(' ', $aliases), $summary]), 0, 20000);
        $now = $this->now();
        $this->db->table('compendium_entry_versions')->insert([
            'entry_id' => $entryId, 'version_number' => $next, 'state' => 'published',
            'type_id' => $this->typeIds[$type], 'parent_entry_id' => null,
            'title' => $title, 'aliases_json' => $this->json($aliases),
            'excerpt' => $summary === '' ? null : mb_substr($summary, 0, 500),
            'visibility' => 'gm_only', 'public_content_json' => self::EMPTY_DOCUMENT,
            'gm_content_json' => self::EMPTY_DOCUMENT, 'public_fields_json' => '{}',
            'gm_fields_json' => $this->json([
                'sourceId' => (string) $record['id'], 'sourceType' => $this->sourceType((string) (($this->candidateMap[$record['id']]['type'] ?? 'lore'))),
            ]),
            'chronology_json' => null, 'start_ordinal' => null, 'end_ordinal' => null,
            'stat_blocks_json' => '[]', 'public_search_text' => null, 'gm_search_text' => $gmSearch,
            'created_by_user_id' => $this->userId, 'published_at' => $now,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function enrichExistingEntity(array $record, array $entity, array $revision): void
    {
        $externalId = (string) $record['id'];
        if (!isset($this->candidateMap[$externalId]) && !isset($this->canonicalMap[$externalId])) return;
        $candidate = $this->candidateMap[$externalId] ?? ['type' => $entity['type_code'] ?? 'lore'];
        $sourceType = $this->sourceType((string) ($candidate['type'] ?? 'lore'));
        $changes = ['updated_at' => $this->now()];
        if (($entity['type_code'] ?? '') !== $sourceType) $changes['type_code'] = $sourceType;
        if (isset($this->canonicalMap[$externalId])
            && ($entity['canonical_id'] ?? '') !== $this->canonicalMap[$externalId]) {
            $changes['canonical_id'] = $this->canonicalMap[$externalId];
        }
        if (count($changes) > 1) {
            $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update($changes);
        }
        if (!empty($revision['entry_version_id'])) {
            $entryType = $this->entryType($sourceType);
            $version = $this->db->table('compendium_entry_versions')->select('type_id')
                ->where('id', (int) $revision['entry_version_id'])->get()->getRowArray();
            if ($version && (int) $version['type_id'] !== (int) $this->typeIds[$entryType]) {
                $this->db->table('compendium_entry_versions')->where('id', (int) $revision['entry_version_id'])
                    ->update(['type_id' => $this->typeIds[$entryType], 'updated_at' => $this->now()]);
            }
        }
    }

    private function replaceNames(int $entityId, int $documentId, string $title, array $aliases): void
    {
        $this->db->table('compendium_entity_names')->where('entity_id', $entityId)
            ->where('source_document_id', $documentId)->whereIn('kind', ['primary', 'alias'])->delete();
        $now = $this->now();
        foreach (array_merge([['primary', $title]], array_map(static function ($name) {
            return ['alias', $name];
        }, $aliases)) as $pair) {
            $name = mb_substr(trim((string) $pair[1]), 0, 180);
            if ($name === '') {
                continue;
            }
            $this->db->table('compendium_entity_names')->ignore(true)->insert([
                'entity_id' => $entityId, 'locale' => 'pl', 'kind' => $pair[0], 'name' => $name,
                'normalized_name' => CompendiumSearchNormalizer::normalize($name),
                'source_document_id' => $documentId, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function replaceEntityCategories(int $entityId, array $categories): void
    {
        $this->db->table('compendium_entity_categories')->where('entity_id', $entityId)->delete();
        foreach ($categories as $name) {
            $normalized = CompendiumSearchNormalizer::normalize($name);
            if (!isset($this->categoryIds[$normalized])) {
                $now = $this->now();
                $this->db->table('compendium_source_categories')->ignore(true)->insert([
                    'source_id' => $this->sourceId, 'name' => mb_substr((string) $name, 0, 180),
                    'normalized_name' => $normalized, 'source_count' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $row = $this->db->table('compendium_source_categories')->where('source_id', $this->sourceId)
                    ->where('normalized_name', $normalized)->get()->getRowArray();
                $this->categoryIds[$normalized] = (int) $row['id'];
            }
            $this->db->table('compendium_entity_categories')->ignore(true)->insert([
                'entity_id' => $entityId, 'category_id' => $this->categoryIds[$normalized],
            ]);
        }
    }

    private function replaceWikiLinks(int $entityId, int $revisionId, array $links): void
    {
        $now = $this->now();
        foreach ($links as $link) {
            if (!is_array($link)) {
                continue;
            }
            $targetExternal = $link['target_id'] ?? null;
            $targetEntity = $targetExternal ? $this->entityByExternalId((string) $targetExternal) : null;
            $this->db->table('compendium_wiki_links')->insert([
                'source_revision_id' => $revisionId, 'from_entity_id' => $entityId,
                'target_entity_id' => $targetEntity, 'target_external_id' => $targetExternal,
                'target_title' => mb_substr((string) ($link['title'] ?? ''), 0, 180), 'anchor' => null,
                'status' => $targetEntity ? 'resolved' : (string) ($link['status'] ?? 'unresolved'),
                'link_type' => 'wiki_link', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function resolveWikiLinks(): int
    {
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $this->db->query(
                'UPDATE compendium_wiki_links l '
                . 'JOIN compendium_entities source_entity ON source_entity.id=l.from_entity_id AND source_entity.world_id=? '
                . 'JOIN compendium_source_documents target_document ON target_document.source_id=? AND target_document.external_id=l.target_external_id '
                . 'JOIN compendium_entities target_entity ON target_entity.source_document_id=target_document.id AND target_entity.world_id=? '
                . "SET l.target_entity_id=target_entity.id,l.status='resolved',l.updated_at=? "
                . 'WHERE l.target_entity_id IS NULL AND l.target_external_id IS NOT NULL',
                [$this->worldId, $this->sourceId, $this->worldId, $this->now()]
            );
            $row = $this->db->query(
                'SELECT COUNT(*) AS aggregate FROM compendium_wiki_links l '
                . 'JOIN compendium_entities source_entity ON source_entity.id=l.from_entity_id '
                . 'WHERE source_entity.world_id=? AND source_entity.current_source_revision_id=l.source_revision_id '
                . 'AND l.target_entity_id IS NULL',
                [$this->worldId]
            )->getRowArray();
            return (int) ($row['aggregate'] ?? 0);
        }
        $links = $this->db->table('compendium_wiki_links l')->select('l.id,l.target_external_id')
            ->join('compendium_entities e', 'e.id=l.from_entity_id', 'inner')
            ->where('e.world_id', $this->worldId)->where('l.target_entity_id', null)
            ->where('e.current_source_revision_id=l.source_revision_id', null, false)
            ->where('l.target_external_id IS NOT NULL', null, false)->get()->getResultArray();
        $unresolved = 0;
        foreach ($links as $link) {
            $entityId = $this->entityByExternalId((string) $link['target_external_id']);
            if ($entityId) {
                $this->db->table('compendium_wiki_links')->where('id', (int) $link['id'])->update([
                    'target_entity_id' => $entityId, 'status' => 'resolved', 'updated_at' => $this->now(),
                ]);
            } else {
                $unresolved++;
            }
        }
        return $unresolved;
    }

    private function importRedirects(array $redirects): int
    {
        $count = 0;
        foreach ($redirects as $redirect) {
            if (!is_array($redirect) || empty($redirect['target_id']) || ($redirect['status'] ?? '') !== 'resolved') {
                continue;
            }
            $entityId = $this->entityByExternalId((string) $redirect['target_id']);
            $name = trim((string) ($redirect['title'] ?? ''));
            if (!$entityId || $name === '') {
                continue;
            }
            $now = $this->now();
            $this->db->table('compendium_entity_names')->ignore(true)->insert([
                'entity_id' => $entityId, 'locale' => 'pl', 'kind' => 'redirect',
                'name' => mb_substr($name, 0, 180), 'normalized_name' => CompendiumSearchNormalizer::normalize($name),
                'source_document_id' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $count++;
        }
        $this->refreshAliasSearch();
        return $count;
    }

    private function importAssets(array $assets): int
    {
        $existingBuilder = $this->db->table('compendium_corpus_assets')
            ->select('id,asset_key,storage_key,author,license');
        if ($this->db->fieldExists('media_asset_id', 'compendium_corpus_assets')) {
            $existingBuilder->select('media_asset_id');
        }
        $existingRows = $existingBuilder->where('source_id', $this->sourceId)->get()->getResultArray();
        $existing = [];
        foreach ($existingRows as $row) $existing[(string) $row['asset_key']] = $row;
        $newRows = [];
        $updates = [];
        $articleIdsByAsset = [];
        foreach ($assets as $asset) {
            if (!is_array($asset) || empty($asset['id']) || empty($asset['filename'])) {
                continue;
            }
            $now = $this->now();
            $key = (string) $asset['id'];
            $data = [
                'filename' => mb_substr((string) $asset['filename'], 0, 255),
                'source_titles_json' => $this->json((array) ($asset['source_titles'] ?? [])),
                'source_url' => $asset['source_url'] ?? null, 'author' => $asset['author'] ?? null,
                'license' => $asset['license'] ?? null, 'role' => 'image',
                'download_status' => (string) ($asset['status'] ?? 'not_downloaded'),
                'metadata_json' => $this->json($asset), 'updated_at' => $now,
            ];
            if (isset($existing[$key])) {
                $existingAsset = $existing[$key];
                if (!empty($existingAsset['storage_key']) || !empty($existingAsset['media_asset_id'])) {
                    unset($data['download_status']);
                }
                if (empty($data['author'])) $data['author'] = $existingAsset['author'];
                if (empty($data['license'])) $data['license'] = $existingAsset['license'];
                $updates[] = $data + ['id' => (int) $existingAsset['id']];
            } else {
                $newRows[] = $data + [
                    'source_id' => $this->sourceId, 'asset_key' => $key,
                    'checksum' => null, 'storage_key' => null, 'mime_type' => null,
                    'byte_size' => null, 'error_message' => null, 'created_at' => $now,
                ];
            }
            $articleIdsByAsset[$key] = array_map('strval', (array) ($asset['article_ids'] ?? []));
        }
        if ($newRows) $this->db->table('compendium_corpus_assets')->insertBatch($newRows, null, 250);
        if ($updates) $this->db->table('compendium_corpus_assets')->updateBatch($updates, 'id', 250);

        $assetRows = $this->db->table('compendium_corpus_assets')->select('id,asset_key')
            ->where('source_id', $this->sourceId)->get()->getResultArray();
        $assetIds = [];
        foreach ($assetRows as $row) $assetIds[(string) $row['asset_key']] = (int) $row['id'];
        $entityRows = $this->db->table('compendium_entities e')->select('e.id,e.current_source_revision_id,d.external_id')
            ->join('compendium_source_documents d', 'd.id=e.source_document_id', 'inner')
            ->where('e.world_id', $this->worldId)->where('d.source_id', $this->sourceId)->get()->getResultArray();
        $entities = [];
        foreach ($entityRows as $row) $entities[(string) $row['external_id']] = $row;
        $pivots = [];
        foreach ($articleIdsByAsset as $key => $articleIds) {
            if (!isset($assetIds[$key])) continue;
            foreach ($articleIds as $externalId) {
                if (!isset($entities[$externalId])) continue;
                $entity = $entities[$externalId];
                $pivots[] = ['entity_id' => (int) $entity['id'], 'asset_id' => $assetIds[$key],
                    'source_revision_id' => (int) $entity['current_source_revision_id'], 'sort_order' => 0];
            }
        }
        $this->upsertEntityAssetPivots($pivots);
        return count($articleIdsByAsset);
    }

    private function upsertEntityAssetPivots(array $pivots): void
    {
        if (!$pivots) return;
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $table = $this->db->prefixTable('compendium_entity_assets');
            foreach (array_chunk($pivots, 500) as $batch) {
                $values = array_map(static function (array $row): string {
                    return '(' . (int) $row['entity_id'] . ',' . (int) $row['asset_id'] . ','
                        . (int) $row['source_revision_id'] . ',' . (int) $row['sort_order'] . ')';
                }, $batch);
                $this->db->query("INSERT INTO `{$table}` (entity_id,asset_id,source_revision_id,sort_order) VALUES "
                    . implode(',', $values) . ' ON DUPLICATE KEY UPDATE source_revision_id=VALUES(source_revision_id),sort_order=VALUES(sort_order)');
            }
            return;
        }
        foreach ($pivots as $pivot) {
            $existing = $this->db->table('compendium_entity_assets')->where('entity_id', $pivot['entity_id'])
                ->where('asset_id', $pivot['asset_id'])->countAllResults();
            if ($existing) {
                $this->db->table('compendium_entity_assets')->where('entity_id', $pivot['entity_id'])
                    ->where('asset_id', $pivot['asset_id'])->update([
                        'source_revision_id' => $pivot['source_revision_id'], 'sort_order' => $pivot['sort_order'],
                    ]);
            } else {
                $this->db->table('compendium_entity_assets')->insert($pivot);
            }
        }
    }

    private function importArchitectureExamples(CompendiumImportPackage $architecture): void
    {
        $citations = [];
        if ($architecture->has('examples/citations.json')) {
            foreach ($architecture->json('examples/citations.json') as $citation) {
                if (is_array($citation) && !empty($citation['id'])) $citations[(string) $citation['id']] = $citation;
            }
        }
        if ($architecture->has('examples/assertions.json')) {
            foreach ($architecture->json('examples/assertions.json') as $assertion) {
                if (!is_array($assertion)) continue;
                $entity = $this->entityByCanonicalId((string) ($assertion['subject_id'] ?? ''));
                if (!$entity) continue;
                $citation = $this->citations((array) ($assertion['citation_ids'] ?? []), $citations);
                $this->upsertAssertion($entity, $assertion, $citation);
            }
        }
        if ($architecture->has('examples/relations.json')) {
            foreach ($architecture->json('examples/relations.json') as $relation) {
                if (!is_array($relation)) continue;
                $subject = $this->entityByCanonicalId((string) ($relation['subject_id'] ?? ''));
                $object = $this->entityByCanonicalId((string) ($relation['object_id'] ?? ''));
                if (!$subject || !$object || $subject === $object) continue;
                $citation = $this->citations((array) ($relation['citation_ids'] ?? []), $citations);
                $this->upsertRelation($subject, $object, $relation, $citation);
            }
        }
        foreach (['08-career.json', '09-spell.json', '10-weapon.json', '11-rolltable.json'] as $name) {
            if (!$architecture->has('examples/' . $name)) {
                continue;
            }
            $example = $architecture->json('examples/' . $name);
            $entity = $this->db->table('compendium_entities')->where('world_id', $this->worldId)
                ->where('canonical_id', (string) ($example['id'] ?? ''))->get()->getRowArray();
            if (!$entity || !is_array($example['mechanics'] ?? null)) {
                continue;
            }
            $mechanics = $example['mechanics'];
            $key = 'architecture:' . pathinfo($name, PATHINFO_FILENAME);
            $data = [
                'entity_id' => (int) $entity['id'], 'system_id' => $this->systemId,
                'kind' => mb_substr((string) ($example['type'] ?? 'profile'), 0, 40), 'variant' => 'default',
                'status' => (string) ($mechanics['status'] ?? 'custom_unverified'),
                'usable' => !empty($mechanics['usable']) ? 1 : 0,
                'profile_json' => $this->json((array) ($mechanics['profile'] ?? [])),
                'source_json' => $this->json(['sourceId' => $mechanics['source_id'] ?? null, 'locator' => $mechanics['locator'] ?? null]),
                'linked_resource_type' => null, 'linked_resource_id' => null, 'updated_at' => $this->now(),
            ];
            $row = $this->db->table('compendium_mechanical_profiles')->where('profile_key', $key)->get()->getRowArray();
            if ($row) {
                $this->db->table('compendium_mechanical_profiles')->where('id', (int) $row['id'])->update($data);
            } else {
                $this->db->table('compendium_mechanical_profiles')->insert($data + ['profile_key' => $key, 'created_at' => $this->now()]);
            }
        }
        if ($architecture->has('examples/assets.json')) {
            foreach ($architecture->json('examples/assets.json') as $asset) {
                if (!is_array($asset)) continue;
                $key = preg_replace('/^asset:/', '', (string) ($asset['id'] ?? ''));
                if ($key === '') continue;
                $this->db->table('compendium_corpus_assets')->where('source_id', $this->sourceId)
                    ->where('asset_key', $key)->update([
                        'role' => mb_substr((string) ($asset['role'] ?? 'image'), 0, 32),
                        'license' => $asset['license'] ?? null, 'updated_at' => $this->now(),
                    ]);
            }
        }
    }

    private function citations(array $ids, array $catalog): array
    {
        return array_values(array_filter(array_map(static function ($id) use ($catalog) {
            return $catalog[(string) $id] ?? null;
        }, $ids)));
    }

    private function upsertAssertion(int $entityId, array $assertion, array $citations): void
    {
        $key = (string) ($assertion['id'] ?? '');
        if ($key === '') return;
        $data = [
            'entity_id' => $entityId, 'predicate' => mb_substr((string) ($assertion['predicate'] ?? ''), 0, 100),
            'value_json' => $this->json($assertion['value'] ?? null),
            'source_revision_id' => $this->citationRevision($citations), 'citation_json' => $this->json($citations),
            'edition' => implode(',', (array) ($assertion['edition'] ?? [])) ?: null,
            'status' => (string) ($assertion['status'] ?? 'proposed'),
            'visibility' => (string) ($assertion['visibility'] ?? 'gm'),
            'valid_time_json' => isset($assertion['valid_time']) ? $this->json($assertion['valid_time']) : null,
            'updated_at' => $this->now(),
        ];
        $row = $this->db->table('compendium_assertions')->where('assertion_key', $key)->get()->getRowArray();
        if ($row) $this->db->table('compendium_assertions')->where('id', (int) $row['id'])->update($data);
        else $this->db->table('compendium_assertions')->insert($data + ['assertion_key' => $key, 'created_at' => $this->now()]);
    }

    private function upsertRelation(int $subjectId, int $objectId, array $relation, array $citations): void
    {
        $key = (string) ($relation['id'] ?? '');
        if ($key === '') return;
        $data = [
            'subject_entity_id' => $subjectId, 'object_entity_id' => $objectId,
            'predicate' => mb_substr((string) ($relation['predicate'] ?? ''), 0, 100),
            'source_revision_id' => $this->citationRevision($citations), 'citation_json' => $this->json($citations),
            'edition' => implode(',', (array) ($relation['edition'] ?? [])) ?: null,
            'status' => (string) ($relation['status'] ?? 'proposed'),
            'canon_status' => (string) ($relation['canon_status'] ?? 'unreviewed'),
            'visibility' => (string) ($relation['visibility'] ?? 'gm'),
            'valid_time_json' => isset($relation['valid_time']) ? $this->json($relation['valid_time']) : null,
            'updated_at' => $this->now(),
        ];
        $row = $this->db->table('compendium_semantic_relations')->where('relation_key', $key)->get()->getRowArray();
        if ($row) $this->db->table('compendium_semantic_relations')->where('id', (int) $row['id'])->update($data);
        else $this->db->table('compendium_semantic_relations')->insert($data + ['relation_key' => $key, 'created_at' => $this->now()]);
    }

    private function citationRevision(array $citations): ?int
    {
        foreach ($citations as $citation) {
            if (!preg_match('/^source:wiki:([0-9]+):([0-9]+)$/', (string) ($citation['source_id'] ?? ''), $match)) continue;
            $row = $this->db->table('compendium_source_revisions sr')->select('sr.id')
                ->join('compendium_source_documents d', 'd.id=sr.document_id', 'inner')
                ->where('d.source_id', $this->sourceId)->where('d.external_id', 'warhammerpl:' . $match[1])
                ->where('sr.external_revision_id', $match[2])->get()->getRowArray();
            if ($row) return (int) $row['id'];
        }
        return null;
    }

    private function entityByCanonicalId(string $canonicalId): ?int
    {
        if ($canonicalId === '') return null;
        $row = $this->db->table('compendium_entities')->select('id')->where('world_id', $this->worldId)
            ->where('canonical_id', $canonicalId)->get()->getRowArray();
        return $row ? (int) $row['id'] : null;
    }

    private function refreshAliasSearch(): void
    {
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $this->db->query(
                'UPDATE compendium_entities ce LEFT JOIN ('
                . "SELECT entity_id,GROUP_CONCAT(normalized_name ORDER BY normalized_name SEPARATOR ' ') AS aliases "
                . "FROM compendium_entity_names WHERE kind IN ('alias','redirect') GROUP BY entity_id"
                . ') names ON names.entity_id=ce.id SET ce.aliases_normalized=names.aliases,ce.updated_at=? WHERE ce.world_id=?',
                [$this->now(), $this->worldId]
            );
            return;
        }
        $entities = $this->db->table('compendium_entities')->select('id')->where('world_id', $this->worldId)->get()->getResultArray();
        foreach ($entities as $entity) {
            $names = $this->db->table('compendium_entity_names')->select('normalized_name')->where('entity_id', (int) $entity['id'])
                ->whereIn('kind', ['alias', 'redirect'])->get()->getResultArray();
            $this->db->table('compendium_entities')->where('id', (int) $entity['id'])
                ->update(['aliases_normalized' => implode(' ', array_column($names, 'normalized_name')), 'updated_at' => $this->now()]);
        }
    }

    private function entityByExternalId(string $externalId): ?int
    {
        if (array_key_exists($externalId, $this->entityIds)) {
            return $this->entityIds[$externalId] ?: null;
        }
        $row = $this->db->table('compendium_entities e')->select('e.id')
            ->join('compendium_source_documents d', 'd.id=e.source_document_id', 'inner')
            ->where('e.world_id', $this->worldId)->where('d.source_id', $this->sourceId)
            ->where('d.external_id', $externalId)->get()->getRowArray();
        $this->entityIds[$externalId] = $row ? (int) $row['id'] : 0;
        return $row ? (int) $row['id'] : null;
    }

    private function sourceType(string $candidate): string
    {
        return in_array($candidate, [
            'polity', 'location', 'character', 'creature', 'faction', 'deity', 'event',
            'career', 'spell', 'weapon', 'rolltable', 'item', 'magic_tradition', 'species',
            'calendar', 'disease', 'language', 'law', 'economy', 'lore',
        ], true) ? $candidate : 'lore';
    }

    private function entryType(string $type): string
    {
        if (in_array($type, ['polity', 'location'], true)) return 'place';
        if ($type === 'character') return 'person';
        if (in_array($type, ['creature', 'species'], true)) return 'creature';
        if (in_array($type, ['faction', 'deity'], true)) return 'faction';
        if ($type === 'event') return 'event';
        if (in_array($type, ['calendar'], true)) return 'history';
        return 'general';
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

    private function resumeRun(int $runId, string $checksum): array
    {
        $run = $this->db->table('compendium_import_runs')->where('id', $runId)
            ->where('world_id', $this->worldId)->get()->getRowArray();
        if (!$run || $run['pack_sha256'] !== $checksum || !in_array($run['status'], ['running', 'failed'], true)) {
            throw new RuntimeException('Import run cannot be resumed for this package.');
        }
        $this->db->table('compendium_import_runs')->where('id', $runId)->update(['status' => 'running', 'updated_at' => $this->now()]);
        return $run;
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

    private function recordItem(int $line, string $externalId, ?array $entity, ?array $revision, string $action, ?int $previousSource, ?int $previousEntry, ?int $entryVersion = null): void
    {
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
            'action' => 'error', 'status' => 'error', 'error_message' => mb_substr($message, 0, 65000),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $base = mb_substr($base ?: 'entry', 0, 170);
        $slug = $base;
        $suffix = 2;
        while ($this->db->table('compendium_entries')->where('world_id', $this->worldId)->where('slug', $slug)->countAllResults()) {
            $slug = mb_substr($base, 0, 170 - strlen((string) $suffix)) . '-' . $suffix++;
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
        $hex = str_replace(['-', '{', '}'], '', $namespace);
        $hash = sha1(hex2bin($hex) . $name);
        return sprintf('%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8), substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    private function nullableText($value, int $limit): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : mb_substr($value, 0, $limit);
    }

    private function json($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            throw new RuntimeException('Data could not be encoded as JSON.');
        }
        return $json;
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
