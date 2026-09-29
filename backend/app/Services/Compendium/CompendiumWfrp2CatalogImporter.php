<?php

namespace App\Services\Compendium;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/** Mirrors current WFRP2 catalogs as immutable, non-usable mechanical profiles. */
final class CompendiumWfrp2CatalogImporter
{
    private const EMPTY_DOCUMENT = '{"type":"doc","content":[{"type":"paragraph","content":[]}]}';
    private const UUID_NAMESPACE = 'e49f0c55-47bb-4a72-a217-5c8dc328bd4f';

    private $db;
    private $worldId;
    private $systemId;
    private $sourceId;
    private $userId;
    private $typeIds = [];
    private $categoryIds = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function sync(string $universeCode = 'old_world', ?int $userId = null): array
    {
        $this->context($universeCode, $userId);
        $this->types();
        $records = [];
        foreach ($this->db->table('professions')->where('system_id', $this->systemId)->orderBy('id')->get()->getResultArray() as $row) {
            $records[] = $this->profession($row);
        }
        foreach ($this->db->table('game_definitions')->where('system_id', $this->systemId)->orderBy('id')->get()->getResultArray() as $row) {
            if (!in_array($row['category'], ['umiejetnosc', 'zdolnosc'], true)) continue;
            $records[] = $this->definition($row);
        }
        foreach ($this->db->table('items')->where('system_id', $this->systemId)->where('deleted_at', null)->orderBy('id')->get()->getResultArray() as $row) {
            $records[] = $this->item($row);
        }
        $now = $this->now();
        $packChecksum = hash('sha256', json_encode(array_map(static function (array $record): array {
            return [$record['externalId'], $record['checksum']];
        }, $records)));
        $this->db->table('compendium_import_runs')->insert([
            'world_id' => $this->worldId, 'source_id' => $this->sourceId,
            'pack_name' => 'BlatyRPG WFRP2 catalog snapshot', 'pack_sha256' => $packChecksum,
            'mode' => 'catalog_sync', 'status' => 'running', 'checkpoint_line' => 0,
            'expected_records' => count($records), 'processed_records' => 0, 'added_records' => 0,
            'updated_records' => 0, 'skipped_records' => 0, 'error_records' => 0,
            'report_json' => '{}', 'started_at' => $now, 'finished_at' => null,
            'rolled_back_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $runId = (int) $this->db->insertID();
        $report = ['processed' => 0, 'added' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => 0, 'errorDetails' => []];
        foreach (array_chunk($records, 50) as $batch) {
            foreach ($batch as $record) {
                $this->db->transBegin();
                try {
                    $action = $this->syncRecord($record, $runId);
                    if ($this->db->transStatus() === false) throw new RuntimeException('Database rejected catalog record.');
                    $this->db->transCommit();
                    $report[$action]++;
                } catch (\Throwable $error) {
                    $this->db->transRollback();
                    $report['errors']++;
                    if (count($report['errorDetails']) < 50) {
                        $report['errorDetails'][] = ['id' => $record['externalId'], 'message' => $error->getMessage()];
                    }
                }
                $report['processed']++;
            }
            $this->db->table('compendium_import_runs')->where('id', $runId)->update([
                'checkpoint_line' => $report['processed'], 'processed_records' => $report['processed'],
                'added_records' => $report['added'], 'updated_records' => $report['updated'],
                'skipped_records' => $report['skipped'], 'error_records' => $report['errors'],
                'report_json' => $this->json($report), 'updated_at' => $this->now(),
            ]);
        }
        $this->db->table('compendium_import_runs')->where('id', $runId)->update([
            'status' => $report['errors'] ? 'completed_with_errors' : 'completed',
            'finished_at' => $this->now(), 'report_json' => $this->json($report), 'updated_at' => $this->now(),
        ]);
        return ['runId' => $runId, 'expected' => count($records), 'report' => $report];
    }

    private function profession(array $row): array
    {
        $professionId = (int) $row['id'];
        $attributes = [];
        foreach ($this->db->table('profession_attributes')->where('profession_id', $professionId)->get()->getResultArray() as $attribute) {
            $attributes[(string) $attribute['attribute_key']] = (int) $attribute['value'];
        }
        $choices = ['skills' => [], 'talents' => []];
        foreach ($this->db->table('profession_definitions pd')->select('pd.metadata,gd.id,gd.category,gd.name')
            ->join('game_definitions gd', 'gd.id=pd.definition_id', 'left')->where('pd.profession_id', $professionId)->get()->getResultArray() as $definition) {
            $meta = $this->decode($definition['metadata']);
            $list = ($definition['category'] ?? '') === 'umiejetnosc' || ($meta['list_type'] ?? '') === 'skills' ? 'skills' : 'talents';
            $choices[$list][] = ['definitionId' => empty($definition['id']) ? null : (int) $definition['id'],
                'name' => $definition['name'] ?? null, 'raw' => $meta['raw'] ?? null, 'metadata' => $meta];
        }
        $paths = ['entry' => [], 'exit' => []];
        foreach ($this->db->table('profession_paths pp')->select('pp.related_profession_id,pp.relation_type,p.name')
            ->join('professions p', 'p.id=pp.related_profession_id', 'inner')->where('pp.profession_id', $professionId)->get()->getResultArray() as $path) {
            $side = $path['relation_type'] === 'entry' ? 'entry' : 'exit';
            $paths[$side][] = ['professionId' => (int) $path['related_profession_id'], 'name' => $path['name']];
        }
        $equipment = [];
        foreach ($this->db->table('profession_equipment pe')->select('pe.*,gd.name AS definition_name')
            ->join('game_definitions gd', 'gd.id=pe.definition_id', 'left')->where('pe.profession_id', $professionId)->get()->getResultArray() as $item) {
            $equipment[] = ['definitionId' => empty($item['definition_id']) ? null : (int) $item['definition_id'],
                'name' => $item['definition_name'] ?: $item['item_name'], 'quantity' => (int) $item['quantity'], 'notes' => $item['notes']];
        }
        $profile = [
            'kind' => !empty($row['is_advanced']) ? 'advanced' : 'basic',
            'isMain' => !empty($row['is_main']), 'advanceScheme' => $attributes,
            'skillChoices' => $choices['skills'], 'talentChoices' => $choices['talents'],
            'entryPaths' => $paths['entry'], 'exitPaths' => $paths['exit'], 'equipment' => $equipment,
            'interpretationNotes' => [
                'Raw alternatives are preserved; the separator does not grant every option.',
                'Empty transitions mean missing repository data, not proof that no transition exists.',
            ],
        ];
        return $this->record('professions', $professionId, 'career', (string) $row['name'],
            (string) ($row['description'] ?: $row['details'] ?: ''), $profile, 'profession', 'Profesje', $row['updated_at'] ?? null);
    }

    private function definition(array $row): array
    {
        $kind = $row['category'] === 'umiejetnosc' ? 'skill' : 'talent';
        $category = $kind === 'skill' ? 'Umiejętności' : 'Zdolności';
        return $this->record('game_definitions', (int) $row['id'], $kind, (string) $row['name'],
            (string) ($row['description'] ?? ''), $this->decode($row['metadata']), $kind,
            $category, $row['updated_at'] ?? null);
    }

    private function item(array $row): array
    {
        $profile = [
            'classId' => empty($row['item_class_id']) ? null : (int) $row['item_class_id'],
            'slot' => $row['slot'], 'price' => $row['price'] === null ? null : (int) $row['price'],
            'availability' => $row['availability'] === null ? null : (int) $row['availability'],
            'code' => $row['code'], 'metadata' => $this->decode($row['metadata']),
        ];
        return $this->record('items', (int) $row['id'], 'item', (string) $row['name'],
            (string) ($row['description'] ?? ''), $profile, 'item', 'Ekwipunek', $row['updated_at'] ?? null);
    }

    private function record(string $table, int $id, string $type, string $name, string $description, array $profile, string $kind, string $category, ?string $updatedAt): array
    {
        $payload = ['table' => $table, 'id' => $id, 'name' => $name, 'description' => $description,
            'type' => $type, 'profile' => $profile, 'updatedAt' => $updatedAt];
        return [
            'externalId' => "repo:{$table}:{$id}", 'canonicalId' => 'entity:' . $this->uuidV5(self::UUID_NAMESPACE, "repo:{$table}:{$id}"),
            'type' => $type, 'name' => mb_substr(trim($name) ?: "#{$id}", 0, 180),
            'description' => $description, 'profile' => $profile, 'kind' => $kind,
            'category' => $category, 'resourceType' => rtrim($table, 's'), 'resourceId' => $id,
            'updatedAt' => $updatedAt, 'payload' => $payload,
            'checksum' => hash('sha256', $this->json($payload)),
        ];
    }

    private function syncRecord(array $record, int $runId): string
    {
        $now = $this->now();
        $document = $this->db->table('compendium_source_documents')->where('source_id', $this->sourceId)
            ->where('external_id', $record['externalId'])->get()->getRowArray();
        if (!$document) {
            $this->db->table('compendium_source_documents')->insert([
                'source_id' => $this->sourceId, 'external_id' => $record['externalId'],
                'source_uri' => null, 'current_revision_id' => null, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $document = ['id' => (int) $this->db->insertID(), 'current_revision_id' => null];
        }
        $entity = $this->db->table('compendium_entities')->where('world_id', $this->worldId)
            ->where('source_document_id', (int) $document['id'])->get()->getRowArray();
        $revision = $this->db->table('compendium_source_revisions')->where('document_id', (int) $document['id'])
            ->where('checksum', $record['checksum'])->get()->getRowArray();
        if ($revision) {
            if ($entity) $this->upsertProfile((int) $entity['id'], $record);
            return 'skipped';
        }
        $html = $record['description'] === '' ? '<p>Brak opisu w katalogu aplikacji.</p>'
            : '<p>' . nl2br(htmlspecialchars($record['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8')) . '</p>';
        $this->db->table('compendium_source_revisions')->insert([
            'document_id' => (int) $document['id'], 'import_run_id' => $runId, 'entry_version_id' => null,
            'external_revision_id' => 'sha256:' . $record['checksum'], 'title' => $record['name'],
            'checksum' => $record['checksum'], 'source_timestamp' => $record['updatedAt'], 'contributor' => null,
            'raw_wikitext' => $record['description'] ?: 'Brak opisu w katalogu aplikacji.',
            'sanitized_html' => $html, 'plain_text' => $record['description'] ?: 'Brak opisu w katalogu aplikacji.',
            'summary' => mb_substr($record['description'], 0, 500) ?: null,
            'sections_json' => $this->json(
                (new CompendiumSourceHtmlSanitizer())->sections($html)
            ),
            'quality_flags_json' => $this->json(['repository_unverified']),
            'source_payload_json' => $this->json($record['payload']), 'validation_status' => 'validated',
            'published_at' => $now, 'created_at' => $now,
        ]);
        $revisionId = (int) $this->db->insertID();
        $action = $entity ? 'updated' : 'added';
        if (!$entity) {
            $slug = $this->uniqueSlug($this->slug($record['name'] . '-' . str_replace(':', '-', $record['externalId'])));
            $this->db->table('compendium_entries')->insert([
                'world_id' => $this->worldId, 'slug' => $slug, 'draft_version_id' => null,
                'published_version_id' => null, 'revision' => 1, 'status' => 'active',
                'created_by_user_id' => $this->userId, 'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entryId = (int) $this->db->insertID();
            $this->db->table('compendium_entities')->insert([
                'world_id' => $this->worldId, 'entry_id' => $entryId, 'source_document_id' => (int) $document['id'],
                'current_source_revision_id' => $revisionId, 'canonical_id' => $record['canonicalId'],
                'type_code' => $record['type'], 'slug' => $slug, 'name' => $record['name'],
                'normalized_name' => CompendiumSearchNormalizer::normalize($record['name']), 'aliases_normalized' => null,
                'search_text_normalized' => CompendiumSearchNormalizer::normalize($record['name'] . ' ' . $record['description'] . ' WFRP2 ' . $record['category']),
                'player_search_normalized' => null, 'player_description' => null, 'gm_notes' => null,
                'visibility' => 'gm', 'spoiler_level' => 'unreviewed', 'editorial_status' => 'repository_unverified',
                'verification_status' => 'unverified', 'canon_status' => 'unreviewed', 'edition' => 'WFRP2',
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entityId = (int) $this->db->insertID();
            $entity = ['id' => $entityId, 'entry_id' => $entryId];
        } else {
            $entryId = (int) $entity['entry_id'];
            $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update([
                'current_source_revision_id' => $revisionId, 'name' => $record['name'],
                'normalized_name' => CompendiumSearchNormalizer::normalize($record['name']),
                'search_text_normalized' => CompendiumSearchNormalizer::normalize($record['name'] . ' ' . $record['description'] . ' WFRP2 ' . $record['category']),
                'updated_at' => $now, 'deleted_at' => null,
            ]);
        }
        $entry = $this->db->table('compendium_entries')->where('id', $entryId)->get()->getRowArray();
        $previous = $entry['published_version_id'] === null ? null : (int) $entry['published_version_id'];
        $next = (int) ($this->db->table('compendium_entry_versions')->selectMax('version_number', 'n')
            ->where('entry_id', $entryId)->get()->getRowArray()['n'] ?? 0) + 1;
        $entryType = in_array($record['type'], ['item'], true) ? 'general' : 'general';
        $this->db->table('compendium_entry_versions')->insert([
            'entry_id' => $entryId, 'version_number' => $next, 'state' => 'published',
            'type_id' => $this->typeIds[$entryType], 'parent_entry_id' => null,
            'title' => $record['name'], 'aliases_json' => '[]',
            'excerpt' => mb_substr($record['description'], 0, 500) ?: null, 'visibility' => 'gm_only',
            'public_content_json' => self::EMPTY_DOCUMENT, 'gm_content_json' => self::EMPTY_DOCUMENT,
            'public_fields_json' => '{}', 'gm_fields_json' => $this->json(['sourceId' => $record['externalId']]),
            'chronology_json' => null, 'start_ordinal' => null, 'end_ordinal' => null,
            'stat_blocks_json' => '[]', 'public_search_text' => null,
            'gm_search_text' => mb_substr($record['name'] . ' ' . $record['description'], 0, 20000),
            'created_by_user_id' => $this->userId, 'published_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $versionId = (int) $this->db->insertID();
        $changes = ['published_version_id' => $versionId, 'updated_at' => $now];
        if (empty($entry['draft_version_id']) || (int) $entry['draft_version_id'] === (int) $previous) $changes['draft_version_id'] = $versionId;
        $this->db->table('compendium_entries')->set('revision', 'revision + 1', false)->where('id', $entryId)->update($changes);
        $this->db->table('compendium_source_revisions')->where('id', $revisionId)->update(['entry_version_id' => $versionId]);
        $this->db->table('compendium_source_documents')->where('id', (int) $document['id'])->update(['current_revision_id' => $revisionId, 'updated_at' => $now]);
        $this->category((int) $entity['id'], $record['category']);
        $this->upsertProfile((int) $entity['id'], $record);
        return $action;
    }

    private function upsertProfile(int $entityId, array $record): void
    {
        $key = 'repo:' . $record['resourceType'] . ':' . $record['resourceId'];
        $data = [
            'entity_id' => $entityId, 'system_id' => $this->systemId, 'kind' => $record['kind'],
            'variant' => 'default', 'status' => 'repository_unverified', 'usable' => 0,
            'profile_json' => $this->json($record['profile']),
            'source_json' => $this->json(['source' => $record['externalId'], 'checksum' => $record['checksum']]),
            'linked_resource_type' => $record['resourceType'], 'linked_resource_id' => $record['resourceId'],
            'updated_at' => $this->now(),
        ];
        $profile = $this->db->table('compendium_mechanical_profiles')->where('profile_key', $key)->get()->getRowArray();
        if ($profile) $this->db->table('compendium_mechanical_profiles')->where('id', (int) $profile['id'])->update($data);
        else $this->db->table('compendium_mechanical_profiles')->insert($data + ['profile_key' => $key, 'created_at' => $this->now()]);
    }

    private function category(int $entityId, string $name): void
    {
        if (!isset($this->categoryIds[$name])) {
            $normalized = CompendiumSearchNormalizer::normalize($name);
            $category = $this->db->table('compendium_source_categories')->where('source_id', $this->sourceId)
                ->where('normalized_name', $normalized)->get()->getRowArray();
            if (!$category) {
                $this->db->table('compendium_source_categories')->insert([
                    'source_id' => $this->sourceId, 'name' => $name, 'normalized_name' => $normalized,
                    'source_count' => 0, 'created_at' => $this->now(), 'updated_at' => $this->now(),
                ]);
                $this->categoryIds[$name] = (int) $this->db->insertID();
            } else $this->categoryIds[$name] = (int) $category['id'];
        }
        $this->db->table('compendium_entity_categories')->ignore(true)->insert([
            'entity_id' => $entityId, 'category_id' => $this->categoryIds[$name],
        ]);
    }

    private function context(string $universeCode, ?int $userId): void
    {
        $system = $this->db->table('rpg_systems')->where('code', 'wfrp2ed')->get()->getRowArray();
        $universe = $this->db->table('rpg_universes')->where('code', $universeCode)->get()->getRowArray();
        if (!$system || !$universe) throw new RuntimeException('wfrp2ed or the selected universe was not found.');
        $world = $this->db->table('compendium_worlds')->where('universe_id', (int) $universe['id'])->get()->getRowArray();
        if (!$world) throw new RuntimeException('Initialize the world Compendium before syncing the catalog.');
        $user = $userId ? $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray() : null;
        if (!$user) $user = $this->db->table('users')->where('role', 'admin')->where('deleted_at', null)->orderBy('id')->get()->getRowArray();
        if (!$user) throw new RuntimeException('No import author is available.');
        $this->systemId = (int) $system['id']; $this->worldId = (int) $world['id']; $this->userId = (int) $user['id'];
        $source = $this->db->table('compendium_sources')->where('source_key', 'blatyrpg-wfrp2-catalog')->get()->getRowArray();
        $data = ['kind' => 'application_catalog', 'name' => 'BlatyRPG — katalog mechaniki WFRP2',
            'language' => 'pl', 'edition' => 'WFRP2', 'base_url' => null, 'license_status' => 'application_data',
            'attribution_status' => 'repository_snapshot', 'metadata_json' => $this->json(['systemCode' => 'wfrp2ed']), 'updated_at' => $this->now()];
        if ($source) {
            $this->sourceId = (int) $source['id'];
            $this->db->table('compendium_sources')->where('id', $this->sourceId)->update($data);
        } else {
            $this->db->table('compendium_sources')->insert($data + ['source_key' => 'blatyrpg-wfrp2-catalog', 'created_at' => $this->now()]);
            $this->sourceId = (int) $this->db->insertID();
        }
    }

    private function types(): void
    {
        foreach ($this->db->table('compendium_entry_types')->select('id,code')->where('world_id', $this->worldId)->get()->getResultArray() as $row) {
            $this->typeIds[(string) $row['code']] = (int) $row['id'];
        }
        if (!isset($this->typeIds['general'])) throw new RuntimeException('Compendium entry types are not initialized.');
    }

    private function decode($json): array
    {
        if (is_array($json)) return $json;
        $value = json_decode((string) $json, true);
        return is_array($value) ? $value : [];
    }

    private function json($value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) throw new RuntimeException('Catalog data could not be encoded.');
        return $json;
    }

    private function slug(string $value): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
        return trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $ascii)), '-') ?: 'entry';
    }

    private function uniqueSlug(string $base): string
    {
        $base = mb_substr($base, 0, 170); $slug = $base; $index = 2;
        while ($this->db->table('compendium_entries')->where('world_id', $this->worldId)->where('slug', $slug)->countAllResults()) {
            $slug = mb_substr($base, 0, 165) . '-' . $index++;
        }
        return $slug;
    }

    private function uuidV5(string $namespace, string $name): string
    {
        $hash = sha1(hex2bin(str_replace('-', '', $namespace)) . $name);
        return sprintf('%08s-%04s-%04x-%04x-%12s', substr($hash, 0, 8), substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000, substr($hash, 20, 12));
    }

    private function now(): string
    {
        return gmdate('Y-m-d H:i:s');
    }
}
