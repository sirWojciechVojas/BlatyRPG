<?php

namespace App\Services\Admin;

use App\Services\Compendium\CompendiumCorpusImporter;
use App\Services\Compendium\CompendiumSearchNormalizer;
use App\Services\Compendium\CompendiumWfrp2CatalogImporter;
use CodeIgniter\Database\BaseConnection;

/** Administrator-only overview and curation operations for every world Compendium. */
final class AdminCompendiumService
{
    private const BUILTIN_TYPES = [
        ['general', 'General', 'book'], ['place', 'Place', 'map'],
        ['person', 'Person', 'user'], ['faction', 'Faction', 'users'],
        ['event', 'Event', 'calendar'], ['history', 'Historical period', 'history'],
        ['creature', 'Creature / NPC', 'paw'],
    ];
    private const VISIBILITIES = ['public', 'player', 'gm', 'secret'];
    private const VERIFICATION_STATUSES = ['unverified', 'verified', 'rejected'];
    private const EDITORIAL_STATUSES = [
        'source_preserved_not_proofread', 'draft', 'needs_review', 'reviewed', 'approved', 'rejected',
    ];
    private const SPOILER_LEVELS = ['unreviewed', 'none', 'minor', 'major', 'secret'];
    private const CANON_STATUSES = ['unreviewed', 'canon', 'non_canon', 'disputed'];
    private const PROFILE_STATUSES = [
        'repository_unverified', 'custom_unverified', 'unverified', 'verified', 'rejected',
    ];

    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function overview(array $auth): array
    {
        $this->verifiedAdmin($auth);
        $universes = $this->db->table('rpg_universes u')
            ->select('u.id,u.code,u.name,u.description,u.default_system_id,s.code AS system_code,s.name AS system_name,'
                . 'game.is_active AS game_is_active,'
                . 'w.id AS world_id,w.owner_user_id,w.storage_limit_bytes,w.updated_at AS world_updated_at,'
                . 'owner.username AS owner_username,owner.email AS owner_email')
            ->join('rpg_systems s', 's.id=u.default_system_id', 'left')
            ->join('rpg_system_universes game', 'game.universe_id=u.id AND game.system_id=u.default_system_id', 'left')
            ->join('compendium_worlds w', 'w.universe_id=u.id', 'left')
            ->join('users owner', 'owner.id=w.owner_user_id AND owner.deleted_at IS NULL', 'left')
            ->orderBy('u.name', 'ASC')->get()->getResultArray();

        $worlds = array_map(function (array $universe): array {
            return $this->worldSummary($universe);
        }, $universes);
        $imports = $this->recentImports();
        $sources = $this->sources();

        return [
            'metrics' => [
                'worlds' => count($worlds),
                'configuredWorlds' => count(array_filter($worlds, static fn (array $world): bool => $world['worldId'] !== null)),
                'entries' => array_sum(array_column($worlds, 'entries')),
                'published' => array_sum(array_column($worlds, 'published')),
                'verified' => array_sum(array_column($worlds, 'verified')),
                'needsReview' => array_sum(array_column($worlds, 'needsReview')),
                'assetsAvailable' => array_sum(array_column($worlds, 'assetsAvailable')),
                'unresolvedLinks' => array_sum(array_column($worlds, 'unresolvedLinks')),
            ],
            'worlds' => $worlds,
            'systems' => $this->systems(),
            'imports' => $imports,
            'sources' => $sources,
            'generatedAt' => date(DATE_ATOM),
        ];
    }

    public function createWorld(array $auth, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new AdminException('validation_failed', 'World name is invalid.', 422, [
                'name' => 'Use between 2 and 100 characters.',
            ]);
        }
        $code = $this->worldCode($payload['code'] ?? null, $name);
        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) > 5000) {
            throw new AdminException('validation_failed', 'World description is invalid.', 422, [
                'description' => 'Use at most 5000 characters.',
            ]);
        }
        $systemId = filter_var($payload['systemId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($systemId === false) {
            throw new AdminException('validation_failed', 'RPG system is required.', 422, [
                'systemId' => 'Select an existing RPG system.',
            ]);
        }
        if (array_key_exists('isActive', $payload) && !is_bool($payload['isActive'])) {
            throw new AdminException('validation_failed', 'Game availability is invalid.', 422, [
                'isActive' => 'Use a boolean value.',
            ]);
        }
        $system = $this->db->table('rpg_systems')->where('id', (int) $systemId)->get()->getRowArray();
        if (!$system) {
            throw new AdminException('validation_failed', 'RPG system is required.', 422, [
                'systemId' => 'Select an existing RPG system.',
            ]);
        }
        if ($this->db->table('rpg_universes')->where('code', $code)->countAllResults()) {
            throw new AdminException('world_code_conflict', 'World code is already in use.', 409, [
                'code' => 'Use a unique world code.',
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $configuredQuota = getenv('COMPENDIUM_WORLD_QUOTA_BYTES');
        $quota = ctype_digit((string) $configuredQuota) ? max(1, (int) $configuredQuota) : 524288000;
        $this->db->transBegin();
        try {
            $this->db->table('rpg_universes')->insert([
                'default_system_id' => (int) $systemId,
                'code' => $code,
                'name' => $name,
                'description' => $description === '' ? null : $description,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $universeId = (int) $this->db->insertID();
            if ($universeId < 1) throw new \RuntimeException('Universe record was not created.');
            $this->db->table('rpg_system_universes')->insert([
                'system_id' => (int) $systemId,
                'universe_id' => $universeId,
                'is_active' => ($payload['isActive'] ?? true) ? 1 : 0,
            ]);
            $this->db->table('compendium_worlds')->insert([
                'universe_id' => $universeId,
                'owner_user_id' => null,
                'storage_limit_bytes' => $quota,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $worldId = (int) $this->db->insertID();
            if ($worldId < 1) throw new \RuntimeException('Compendium world was not created.');
            $this->db->table('compendium_calendars')->insert([
                'world_id' => $worldId,
                'name' => 'Calendar',
                'structure_locked' => 0,
                'revision' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            foreach (self::BUILTIN_TYPES as $order => [$typeCode, $typeName, $icon]) {
                $this->db->table('compendium_entry_types')->insert([
                    'world_id' => $worldId,
                    'code' => $typeCode,
                    'name' => $typeName,
                    'icon' => $icon,
                    'is_builtin' => 1,
                    'field_schema_json' => '[]',
                    'sort_order' => $order,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new \RuntimeException('Database rejected the new world.');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new AdminException('world_create_failed', 'World could not be created.', 500);
        }

        return ['world' => [
            'universeId' => $universeId,
            'worldId' => $worldId,
            'code' => $code,
            'name' => $name,
            'systemId' => (int) $systemId,
            'systemCode' => (string) $system['code'],
            'systemName' => (string) $system['name'],
            'description' => $description === '' ? null : $description,
            'gameIsActive' => (bool) ($payload['isActive'] ?? true),
            'owner' => null,
            'storageLimitBytes' => $quota,
        ]];
    }

    public function updateWorld(array $auth, int $universeId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $universe = $this->db->table('rpg_universes')->where('id', $universeId)->get()->getRowArray();
        if (!$universe) throw new AdminException('compendium_not_found', 'World was not found.', 404);
        $name = trim((string) ($payload['name'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
            throw new AdminException('validation_failed', 'World name is invalid.', 422, [
                'name' => 'Use between 2 and 100 characters.',
            ]);
        }
        $description = trim((string) ($payload['description'] ?? ''));
        if (mb_strlen($description) > 5000) {
            throw new AdminException('validation_failed', 'World description is invalid.', 422, [
                'description' => 'Use at most 5000 characters.',
            ]);
        }
        $systemId = filter_var($payload['systemId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($systemId === false || !($system = $this->db->table('rpg_systems')
            ->where('id', (int) $systemId)->get()->getRowArray())) {
            throw new AdminException('validation_failed', 'RPG system is required.', 422, [
                'systemId' => 'Select an existing RPG system.',
            ]);
        }
        if (!array_key_exists('isActive', $payload) || !is_bool($payload['isActive'])) {
            throw new AdminException('validation_failed', 'Game availability is invalid.', 422, [
                'isActive' => 'Use a boolean value.',
            ]);
        }

        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('rpg_universes')->where('id', $universeId)->update([
                'default_system_id' => (int) $systemId,
                'name' => $name,
                'description' => $description === '' ? null : $description,
                'updated_at' => $now,
            ]);
            $game = $this->db->table('rpg_system_universes')
                ->where('system_id', (int) $systemId)->where('universe_id', $universeId)
                ->countAllResults();
            if ($game) {
                $this->db->table('rpg_system_universes')
                    ->where('system_id', (int) $systemId)->where('universe_id', $universeId)
                    ->update(['is_active' => $payload['isActive'] ? 1 : 0]);
            } else {
                $this->db->table('rpg_system_universes')->insert([
                    'system_id' => (int) $systemId,
                    'universe_id' => $universeId,
                    'is_active' => $payload['isActive'] ? 1 : 0,
                ]);
            }
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw new \RuntimeException('Database rejected the world changes.');
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new AdminException('world_update_failed', 'World could not be updated.', 500);
        }

        return ['world' => [
            'universeId' => $universeId,
            'code' => (string) $universe['code'],
            'name' => $name,
            'description' => $description === '' ? null : $description,
            'systemId' => (int) $systemId,
            'systemCode' => (string) $system['code'],
            'systemName' => (string) $system['name'],
            'gameIsActive' => $payload['isActive'],
        ]];
    }

    public function updateEntryPolicy(array $auth, int $universeId, int $entryId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $entity = $this->entityForEntry($universeId, $entryId);
        $updates = [];
        if (array_key_exists('visibility', $payload)) {
            $updates['visibility'] = $this->enum($payload['visibility'], self::VISIBILITIES, 'visibility');
        }
        if (array_key_exists('verificationStatus', $payload)) {
            $updates['verification_status'] = $this->enum(
                $payload['verificationStatus'], self::VERIFICATION_STATUSES, 'verificationStatus'
            );
        }
        if (array_key_exists('editorialStatus', $payload)) {
            $updates['editorial_status'] = $this->enum(
                $payload['editorialStatus'], self::EDITORIAL_STATUSES, 'editorialStatus'
            );
        }
        if (array_key_exists('spoilerLevel', $payload)) {
            $updates['spoiler_level'] = $this->enum(
                $payload['spoilerLevel'], self::SPOILER_LEVELS, 'spoilerLevel'
            );
        }
        if (array_key_exists('canonStatus', $payload)) {
            $updates['canon_status'] = $this->enum(
                $payload['canonStatus'], self::CANON_STATUSES, 'canonStatus'
            );
        }
        if (array_key_exists('typeCode', $payload)) {
            $type = strtolower(trim((string) $payload['typeCode']));
            if (!preg_match('/^[a-z][a-z0-9_]{1,63}$/', $type)) {
                throw new AdminException('validation_failed', 'Entry type is invalid.', 422, [
                    'typeCode' => 'Use a stable lower-case code.',
                ]);
            }
            $updates['type_code'] = $type;
        }
        if (array_key_exists('edition', $payload)) {
            $edition = trim((string) ($payload['edition'] ?? ''));
            if (mb_strlen($edition) > 32) {
                throw new AdminException('validation_failed', 'Edition is invalid.', 422, [
                    'edition' => 'Use at most 32 characters.',
                ]);
            }
            $updates['edition'] = $edition === '' ? null : $edition;
        }
        if (array_key_exists('playerDescription', $payload)) {
            $updates['player_description'] = $this->longText($payload['playerDescription'], 'playerDescription');
        }
        if (array_key_exists('gmNotes', $payload)) {
            $updates['gm_notes'] = $this->longText($payload['gmNotes'], 'gmNotes');
        }
        if (!$updates) {
            throw new AdminException('validation_failed', 'No supported fields were provided.', 422);
        }
        if (array_key_exists('player_description', $updates)) {
            $updates['player_search_normalized'] = CompendiumSearchNormalizer::normalize(implode(' ', [
                (string) $entity['name'], (string) ($entity['aliases_normalized'] ?? ''),
                (string) ($updates['player_description'] ?? ''),
            ]));
        }
        $updates['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('compendium_entities')->where('id', (int) $entity['id'])->update($updates);
        return ['entryPolicy' => $this->presentEntityPolicy($this->entityForEntry($universeId, $entryId))];
    }

    public function updateMechanicalProfile(
        array $auth,
        int $universeId,
        int $profileId,
        array $payload
    ): array {
        $this->verifiedAdmin($auth);
        $profile = $this->db->table('compendium_mechanical_profiles p')
            ->select('p.*,e.world_id,w.universe_id')
            ->join('compendium_entities e', 'e.id=p.entity_id', 'inner')
            ->join('compendium_worlds w', 'w.id=e.world_id', 'inner')
            ->where('p.id', $profileId)->where('w.universe_id', $universeId)
            ->get()->getRowArray();
        if (!$profile) {
            throw new AdminException('compendium_profile_not_found', 'Mechanical profile was not found.', 404);
        }
        $status = array_key_exists('status', $payload)
            ? $this->enum($payload['status'], self::PROFILE_STATUSES, 'status')
            : (string) $profile['status'];
        if (array_key_exists('usable', $payload) && !is_bool($payload['usable'])) {
            throw new AdminException('validation_failed', 'Profile availability is invalid.', 422, [
                'usable' => 'Use a JSON boolean value.',
            ]);
        }
        $usable = array_key_exists('usable', $payload)
            ? $payload['usable'] : !empty($profile['usable']);
        if ($usable && $status !== 'verified') {
            throw new AdminException('validation_failed', 'Only a verified profile may be usable.', 422, [
                'usable' => 'Verify the profile before enabling automation.',
            ]);
        }
        $this->db->table('compendium_mechanical_profiles')->where('id', $profileId)->update([
            'status' => $status, 'usable' => $usable ? 1 : 0, 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return ['profile' => ['id' => $profileId, 'status' => $status, 'usable' => $usable]];
    }

    public function updateSource(array $auth, int $sourceId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $source = $this->db->table('compendium_sources')->where('id', $sourceId)->get()->getRowArray();
        if (!$source) throw new AdminException('compendium_source_not_found', 'Source was not found.', 404);
        $updates = [];
        foreach (['licenseStatus' => 'license_status', 'attributionStatus' => 'attribution_status'] as $input => $column) {
            if (!array_key_exists($input, $payload)) continue;
            $value = trim((string) $payload[$input]);
            if ($value === '' || mb_strlen($value) > 64) {
                throw new AdminException('validation_failed', 'Source status is invalid.', 422, [
                    $input => 'Use between 1 and 64 characters.',
                ]);
            }
            $updates[$column] = $value;
        }
        if (!$updates) throw new AdminException('validation_failed', 'No supported fields were provided.', 422);
        $updates['updated_at'] = date('Y-m-d H:i:s');
        $this->db->table('compendium_sources')->where('id', $sourceId)->update($updates);
        return ['source' => $this->presentSource(
            $this->db->table('compendium_sources')->where('id', $sourceId)->get()->getRowArray()
        )];
    }

    public function rollbackImport(array $auth, int $universeId, int $runId): array
    {
        $this->verifiedAdmin($auth);
        $run = $this->db->table('compendium_import_runs r')->select('r.*,w.universe_id')
            ->join('compendium_worlds w', 'w.id=r.world_id', 'inner')
            ->where('r.id', $runId)->where('w.universe_id', $universeId)->get()->getRowArray();
        if (!$run) throw new AdminException('compendium_import_not_found', 'Import run was not found.', 404);
        try {
            return (new CompendiumCorpusImporter($this->db))->rollback($runId);
        } catch (\Throwable $exception) {
            throw new AdminException('compendium_import_rollback_failed', $exception->getMessage(), 409);
        }
    }

    public function syncWfrp2Catalog(array $auth, int $universeId): array
    {
        $admin = $this->verifiedAdmin($auth);
        $universe = $this->db->table('rpg_universes')->where('id', $universeId)->get()->getRowArray();
        if (!$universe) throw new AdminException('compendium_not_found', 'World was not found.', 404);
        try {
            return (new CompendiumWfrp2CatalogImporter($this->db))->sync(
                (string) $universe['code'], (int) $admin['id']
            );
        } catch (\Throwable $exception) {
            throw new AdminException('compendium_catalog_sync_failed', $exception->getMessage(), 422);
        }
    }

    private function worldSummary(array $universe): array
    {
        $worldId = (int) ($universe['world_id'] ?? 0);
        $empty = [
            'entries' => 0, 'published' => 0, 'drafts' => 0, 'archived' => 0,
            'sourceEntries' => 0, 'verified' => 0, 'needsReview' => 0,
            'assetsRegistered' => 0, 'assetsAvailable' => 0, 'usedBytes' => 0,
            'unresolvedLinks' => 0, 'mechanicalProfiles' => 0, 'usableProfiles' => 0,
            'editors' => 0, 'campaigns' => 0, 'lastImport' => null,
        ];
        $result = [
            'universeId' => (int) $universe['id'],
            'worldId' => $worldId ?: null,
            'code' => (string) $universe['code'],
            'name' => (string) $universe['name'],
            'description' => $universe['description'] ?? null,
            'systemId' => $universe['default_system_id'] === null ? null : (int) $universe['default_system_id'],
            'systemCode' => $universe['system_code'],
            'systemName' => $universe['system_name'],
            'gameIsActive' => !empty($universe['game_is_active']),
            'owner' => $universe['owner_user_id'] === null ? null : [
                'userId' => (int) $universe['owner_user_id'],
                'username' => $universe['owner_username'],
                'email' => $universe['owner_email'],
            ],
            'storageLimitBytes' => (int) ($universe['storage_limit_bytes'] ?? 0),
            'updatedAt' => $universe['world_updated_at'] ?? null,
        ] + $empty;
        $result['campaigns'] = (int) $this->db->table('campaigns')
            ->where('rpg_universe_id', (int) $universe['id'])->countAllResults();
        if (!$worldId) return $result;

        $entryStats = $this->db->query(
            'SELECT COUNT(*) total,SUM(status="active" AND published_version_id IS NOT NULL) published,'
            . 'SUM(draft_version_id IS NOT NULL AND (published_version_id IS NULL OR draft_version_id<>published_version_id)) drafts,'
            . 'SUM(status="archived") archived FROM compendium_entries WHERE world_id=?', [$worldId]
        )->getRowArray();
        $entityStats = $this->db->query(
            'SELECT COUNT(*) total,SUM(verification_status="verified") verified,'
            . 'SUM(editorial_status IN ("source_preserved_not_proofread","draft","needs_review")) needs_review '
            . 'FROM compendium_entities WHERE world_id=? AND deleted_at IS NULL', [$worldId]
        )->getRowArray();
        $profileStats = $this->db->query(
            'SELECT COUNT(*) total,SUM(p.usable=1 AND p.status="verified") usable '
            . 'FROM compendium_mechanical_profiles p JOIN compendium_entities e ON e.id=p.entity_id '
            . 'WHERE e.world_id=? AND e.deleted_at IS NULL', [$worldId]
        )->getRowArray();
        $assetRows = $this->db->query(
            'SELECT DISTINCT a.id,a.storage_key,a.byte_size FROM compendium_corpus_assets a '
            . 'JOIN compendium_entity_assets ea ON ea.asset_id=a.id '
            . 'JOIN compendium_entities e ON e.id=ea.entity_id WHERE e.world_id=? AND e.deleted_at IS NULL',
            [$worldId]
        )->getResultArray();
        $authoredAssets = $this->db->table('compendium_assets')->select('byte_size')
            ->where('world_id', $worldId)->where('deleted_at', null)->get()->getResultArray();
        $lastImport = $this->db->table('compendium_import_runs')->where('world_id', $worldId)
            ->orderBy('id', 'DESC')->get()->getRowArray();

        return array_merge($result, [
            'entries' => (int) ($entryStats['total'] ?? 0),
            'published' => (int) ($entryStats['published'] ?? 0),
            'drafts' => (int) ($entryStats['drafts'] ?? 0),
            'archived' => (int) ($entryStats['archived'] ?? 0),
            'sourceEntries' => (int) ($entityStats['total'] ?? 0),
            'verified' => (int) ($entityStats['verified'] ?? 0),
            'needsReview' => (int) ($entityStats['needs_review'] ?? 0),
            'assetsRegistered' => count($assetRows),
            'assetsAvailable' => count(array_filter($assetRows, static fn (array $asset): bool => !empty($asset['storage_key']))),
            'usedBytes' => array_sum(array_map(static fn (array $asset): int => (int) ($asset['byte_size'] ?? 0), array_merge($assetRows, $authoredAssets))),
            'unresolvedLinks' => (int) $this->db->table('compendium_wiki_links l')
                ->join('compendium_entities e', 'e.id=l.from_entity_id', 'inner')
                ->where('e.world_id', $worldId)->where('l.status', 'unresolved')->countAllResults(),
            'mechanicalProfiles' => (int) ($profileStats['total'] ?? 0),
            'usableProfiles' => (int) ($profileStats['usable'] ?? 0),
            'editors' => (int) $this->db->table('compendium_editors')->where('world_id', $worldId)->countAllResults(),
            'lastImport' => $lastImport ? $this->presentImport($lastImport, $result['name']) : null,
        ]);
    }

    private function recentImports(): array
    {
        return array_map(function (array $row): array {
            return $this->presentImport($row, (string) $row['universe_name']);
        }, $this->db->table('compendium_import_runs r')
            ->select('r.*,w.universe_id,u.name AS universe_name')
            ->join('compendium_worlds w', 'w.id=r.world_id', 'inner')
            ->join('rpg_universes u', 'u.id=w.universe_id', 'inner')
            ->orderBy('r.id', 'DESC')->limit(50)->get()->getResultArray());
    }

    private function sources(): array
    {
        return array_map(function (array $row): array {
            $source = $this->presentSource($row);
            $source['documents'] = (int) $row['document_count'];
            $source['revisions'] = (int) $row['revision_count'];
            return $source;
        }, $this->db->table('compendium_sources s')
            ->select('s.*,COUNT(DISTINCT d.id) AS document_count,COUNT(DISTINCT r.id) AS revision_count')
            ->join('compendium_source_documents d', 'd.source_id=s.id', 'left')
            ->join('compendium_source_revisions r', 'r.document_id=d.id', 'left')
            ->groupBy('s.id')->orderBy('s.name', 'ASC')->get()->getResultArray());
    }

    private function systems(): array
    {
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'code' => (string) $row['code'],
            'name' => (string) $row['name'],
        ], $this->db->table('rpg_systems')->select('id,code,name')->orderBy('name', 'ASC')->get()->getResultArray());
    }

    private function presentImport(array $row, string $worldName): array
    {
        return [
            'id' => (int) $row['id'], 'universeId' => isset($row['universe_id']) ? (int) $row['universe_id'] : null,
            'worldName' => $worldName, 'packName' => (string) $row['pack_name'], 'mode' => (string) $row['mode'],
            'status' => (string) $row['status'], 'expected' => (int) $row['expected_records'],
            'processed' => (int) $row['processed_records'], 'added' => (int) $row['added_records'],
            'updated' => (int) $row['updated_records'], 'skipped' => (int) $row['skipped_records'],
            'errors' => (int) $row['error_records'], 'checkpoint' => (int) $row['checkpoint_line'],
            'startedAt' => $row['started_at'], 'finishedAt' => $row['finished_at'],
            'rolledBackAt' => $row['rolled_back_at'],
        ];
    }

    private function presentSource(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'key' => (string) $row['source_key'], 'kind' => (string) $row['kind'],
            'name' => (string) $row['name'], 'language' => (string) $row['language'], 'edition' => $row['edition'],
            'baseUrl' => $row['base_url'], 'licenseStatus' => (string) $row['license_status'],
            'attributionStatus' => (string) $row['attribution_status'], 'updatedAt' => $row['updated_at'],
        ];
    }

    private function entityForEntry(int $universeId, int $entryId): array
    {
        $row = $this->db->table('compendium_entities e')->select('e.*,w.universe_id')
            ->join('compendium_worlds w', 'w.id=e.world_id', 'inner')
            ->where('e.entry_id', $entryId)->where('w.universe_id', $universeId)
            ->where('e.deleted_at', null)->get()->getRowArray();
        if (!$row) throw new AdminException('compendium_entry_not_found', 'Source-backed entry was not found.', 404);
        return $row;
    }

    private function presentEntityPolicy(array $row): array
    {
        return [
            'entryId' => (int) $row['entry_id'], 'entityId' => (int) $row['id'],
            'typeCode' => (string) $row['type_code'], 'visibility' => (string) $row['visibility'],
            'verificationStatus' => (string) $row['verification_status'],
            'editorialStatus' => (string) $row['editorial_status'],
            'spoilerLevel' => (string) $row['spoiler_level'], 'canonStatus' => (string) $row['canon_status'],
            'edition' => $row['edition'], 'playerDescription' => $row['player_description'],
            'gmNotes' => $row['gm_notes'], 'updatedAt' => $row['updated_at'],
        ];
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray();
        if (!$user) throw new AdminException('unauthorized', 'Authentication is required.', 401);
        if (strtolower((string) $user['role']) !== 'admin') {
            throw new AdminException('forbidden', 'Administrator access is required.', 403);
        }
        return $user;
    }

    private function enum($value, array $allowed, string $field): string
    {
        $result = strtolower(trim((string) $value));
        if (!in_array($result, $allowed, true)) {
            throw new AdminException('validation_failed', 'Compendium policy is invalid.', 422, [
                $field => 'Select a supported value.',
            ]);
        }
        return $result;
    }

    private function longText($value, string $field): ?string
    {
        $result = trim((string) ($value ?? ''));
        if (mb_strlen($result) > 100000) {
            throw new AdminException('validation_failed', 'Compendium text is too long.', 422, [
                $field => 'Use at most 100000 characters.',
            ]);
        }
        return $result === '' ? null : $result;
    }

    private function worldCode($value, string $name): string
    {
        $provided = trim((string) ($value ?? ''));
        if ($provided !== '') {
            $code = strtolower($provided);
            if (!preg_match('/^[a-z][a-z0-9_]{0,49}$/', $code)) {
                throw new AdminException('validation_failed', 'World code is invalid.', 422, [
                    'code' => 'Use lower-case letters, digits and underscores; start with a letter.',
                ]);
            }
            return $code;
        }
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name);
        $code = strtolower($ascii === false ? $name : $ascii);
        $code = trim((string) preg_replace('/[^a-z0-9]+/', '_', $code), '_');
        if ($code === '' || !preg_match('/^[a-z]/', $code)) $code = 'world_' . $code;
        return substr($code, 0, 50);
    }
}
