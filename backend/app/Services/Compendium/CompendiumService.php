<?php

namespace App\Services\Compendium;

use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;

final class CompendiumService
{
    private const BUILTIN_TYPES = [
        ['general', 'General', 'book'], ['place', 'Place', 'map'],
        ['person', 'Person', 'user'], ['faction', 'Faction', 'users'],
        ['event', 'Event', 'calendar'], ['history', 'Historical period', 'history'],
        ['creature', 'Creature / NPC', 'paw'],
    ];
    private const FIELD_TYPES = ['text', 'number', 'boolean', 'select', 'multiselect', 'date', 'relation'];
    private const EMPTY_DOCUMENT = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => []]]];

    private $db;
    private $access;
    private $documents;

    public function __construct(
        ?BaseConnection $db = null,
        ?CompendiumAccessService $access = null,
        ?CompendiumDocumentValidator $documents = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new CompendiumAccessService($this->db);
        $this->documents = $documents ?: new CompendiumDocumentValidator();
    }

    public function campaignIndex(int $campaignId, array $auth, array $query = []): array
    {
        return $this->index($this->access->campaign($auth, $campaignId), $query, false);
    }

    public function campaignShow(int $campaignId, int $entryId, array $auth): array
    {
        return ['entry' => $this->presentEntry($this->access->campaign($auth, $campaignId), $entryId, false)];
    }

    public function campaignTimeline(int $campaignId, array $auth, array $query = []): array
    {
        $query['dated'] = true;
        $query['sort'] = 'timeline';
        return $this->campaignIndex($campaignId, $auth, $query);
    }

    public function editorialIndex(int $universeId, array $auth, array $query = []): array
    {
        return $this->index($this->editorialContext($auth, $universeId), $query, true);
    }

    public function editorialShow(int $universeId, int $entryId, array $auth): array
    {
        $context = $this->editorialContext($auth, $universeId);
        return [
            'entry' => $this->presentEntry($context, $entryId, true),
            'history' => $this->historyRows($context, $entryId),
        ];
    }

    public function overview(int $universeId, array $auth): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $world = $context['world'];
        $universe = $this->db->table('rpg_universes')->where('id', $universeId)->get()->getRowArray();
        return [
            'world' => [
                'id' => (int) $world['id'], 'universeId' => $universeId,
                'name' => (string) ($universe['name'] ?? ''),
                'ownerUserId' => $world['owner_user_id'] === null ? null : (int) $world['owner_user_id'],
                'storageLimitBytes' => (int) $world['storage_limit_bytes'],
                'revision' => (int) $world['revision'],
            ],
            'types' => $this->types((int) $world['id']),
            'tags' => $this->tags((int) $world['id']),
            'calendar' => $this->calendar((int) $world['id']),
            'editors' => $context['canManageEditors'] ? $this->editors((int) $world['id']) : [],
            'capabilities' => $this->capabilities($context),
        ];
    }

    public function campaignOverview(int $campaignId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $this->bootstrap((int) $context['world']['id']);
        $universe = $this->db->table('rpg_universes')
            ->where('id', (int) $context['world']['universe_id'])->get()->getRowArray();
        return [
            'world' => ['id' => (int) $context['world']['id'],
                'universeId' => (int) $context['world']['universe_id'],
                'name' => (string) ($universe['name'] ?? '')],
            'types' => $this->types((int) $context['world']['id']),
            'tags' => $this->tags((int) $context['world']['id']),
            'calendar' => $this->calendar((int) $context['world']['id']),
            'capabilities' => $this->capabilities($context),
        ];
    }

    public function mine(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        $admin = strtolower((string) ($auth['role'] ?? '')) === 'admin';
        $builder = $this->db->table('compendium_worlds w')
            ->select('w.*, u.name AS universe_name, u.code AS universe_code')
            ->join('rpg_universes u', 'u.id = w.universe_id', 'inner');
        if (!$admin) {
            $builder->join('compendium_editors e', 'e.world_id = w.id AND e.user_id = ' . $userId, 'left')
                ->groupStart()->where('w.owner_user_id', $userId)->orWhere('e.user_id', $userId)->groupEnd();
        }
        $rows = $builder->orderBy('u.name', 'ASC')->get()->getResultArray();
        return ['items' => array_map(static fn (array $row): array => [
            'worldId' => (int) $row['id'], 'universeId' => (int) $row['universe_id'],
            'name' => (string) $row['universe_name'], 'code' => (string) $row['universe_code'],
            'isOwner' => (int) ($row['owner_user_id'] ?? 0) === $userId,
        ], $rows)];
    }

    public function createEntry(int $universeId, array $auth, array $payload): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $worldId = (int) $context['world']['id'];
        $data = $this->entryPayload($context, $payload, null);
        $slug = $this->uniqueSlug($worldId, (string) ($payload['slug'] ?? $data['version']['title']));
        $now = $this->now();
        $this->db->transBegin();
        try {
            $this->db->table('compendium_entries')->insert([
                'world_id' => $worldId, 'slug' => $slug, 'draft_version_id' => null,
                'published_version_id' => null, 'revision' => 1, 'status' => 'active',
                'created_by_user_id' => (int) $context['auth']['user_id'],
                'created_at' => $now, 'updated_at' => $now, 'deleted_at' => null,
            ]);
            $entryId = (int) $this->db->insertID();
            $data['version']['entry_id'] = $entryId;
            $versionId = $this->insertVersion($context, $data['version'], $data);
            $this->db->table('compendium_entries')->where('id', $entryId)->update(['draft_version_id' => $versionId]);
            $this->commit('compendium_write_failed');
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['entry' => $this->presentEntry($context, $entryId, true)];
    }

    public function updateEntry(int $universeId, int $entryId, array $auth, array $payload): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $entry = $this->entry($context, $entryId);
        $revision = $this->requiredRevision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $this->db->transBegin();
        try {
            $draftId = (int) $entry['draft_version_id'];
            if ($draftId === (int) ($entry['published_version_id'] ?? 0)) {
                $draftId = $this->cloneVersion($context, $entryId, $draftId);
                $this->db->table('compendium_entries')->where('id', $entryId)->update(['draft_version_id' => $draftId]);
            }
            $existing = $this->version($draftId);
            $data = $this->entryPayload($context, $payload, $existing);
            $data['version']['entry_id'] = $entryId;
            $this->db->table('compendium_entry_versions')->where('id', $draftId)->where('state', 'draft')
                ->update($data['version'] + ['updated_at' => $this->now()]);
            $this->replaceAssociations($context, $draftId, $data);
            $this->db->table('compendium_entries')->set('revision', 'revision + 1', false)
                ->set('updated_at', $this->now())->where('id', $entryId)->where('revision', $revision)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictFor($entryId);
            $this->commit('compendium_write_failed');
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['entry' => $this->presentEntry($context, $entryId, true)];
    }

    public function publish(int $universeId, int $entryId, array $auth, array $payload): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $entry = $this->entry($context, $entryId);
        $revision = $this->requiredRevision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $draft = $this->version((int) $entry['draft_version_id']);
        if (($draft['state'] ?? '') !== 'draft') throw new CampaignException('nothing_to_publish', 'There are no unpublished changes.', 409);
        $this->validatePublish($context, $entryId, $draft);
        $next = (int) ($this->db->table('compendium_entry_versions')->selectMax('version_number', 'n')
            ->where('entry_id', $entryId)->get()->getRowArray()['n'] ?? 0) + 1;
        $now = $this->now();
        $this->db->transBegin();
        try {
            $this->db->table('compendium_entry_versions')->where('id', (int) $draft['id'])->update([
                'state' => 'published', 'version_number' => $next,
                'published_at' => $now, 'updated_at' => $now,
            ]);
            $this->db->table('compendium_entries')->set([
                'published_version_id' => (int) $draft['id'], 'updated_at' => $now,
            ])->set('revision', 'revision + 1', false)->where('id', $entryId)
                ->where('revision', $revision)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictFor($entryId);
            if (!empty($draft['chronology_json'])) $this->db->table('compendium_calendars')
                ->where('world_id', (int) $context['world']['id'])->update(['structure_locked' => 1, 'updated_at' => $now]);
            $this->commit('compendium_publish_failed');
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['entry' => $this->presentEntry($context, $entryId, true), 'versionNumber' => $next];
    }

    public function restoreVersion(int $universeId, int $entryId, int $versionId, array $auth, array $payload): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $entry = $this->entry($context, $entryId);
        $revision = $this->requiredRevision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $source = $this->version($versionId);
        if ((int) $source['entry_id'] !== $entryId || $source['state'] !== 'published') throw new CampaignException('compendium_version_not_found', 'Version was not found.', 404);
        $this->db->transBegin();
        try {
            $draftId = $this->cloneVersion($context, $entryId, $versionId);
            $this->db->table('compendium_entries')->set(['draft_version_id' => $draftId, 'updated_at' => $this->now()])
                ->set('revision', 'revision + 1', false)->where('id', $entryId)->where('revision', $revision)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictFor($entryId);
            $this->commit('compendium_write_failed');
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['entry' => $this->presentEntry($context, $entryId, true)];
    }

    public function archive(int $universeId, int $entryId, array $auth, array $payload, bool $restore = false): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $entry = $this->entry($context, $entryId);
        $revision = $this->requiredRevision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $this->db->table('compendium_entries')->set([
            'status' => $restore ? 'active' : 'archived',
            'deleted_at' => $restore ? null : $this->now(), 'updated_at' => $this->now(),
        ])->set('revision', 'revision + 1', false)->where('id', $entryId)->where('revision', $revision)->update();
        if ($this->db->affectedRows() !== 1) $this->conflictFor($entryId);
        return ['entry' => $this->presentEntry($context, $entryId, true)];
    }

    public function createTag(int $universeId, array $auth, array $payload): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $name = $this->name($payload['name'] ?? '', 80, 'name');
        $color = $this->color($payload['color'] ?? null);
        $now = $this->now();
        if (!$this->db->table('compendium_tags')->insert(['world_id' => (int) $context['world']['id'],
            'name' => $name, 'color' => $color, 'created_at' => $now, 'updated_at' => $now])) {
            throw new CampaignException('validation_failed', 'Tag already exists.', 422, ['name' => 'Name must be unique.']);
        }
        return ['tag' => ['id' => (int) $this->db->insertID(), 'name' => $name, 'color' => $color]];
    }

    public function deleteTag(int $universeId, int $tagId, array $auth): array
    {
        $context = $this->editorialContext($auth, $universeId);
        $this->ownedRow('compendium_tags', $tagId, (int) $context['world']['id'], 'compendium_tag_not_found');
        $this->db->table('compendium_tags')->where('id', $tagId)->delete();
        return ['deleted' => true, 'id' => $tagId];
    }

    public function createType(int $universeId, array $auth, array $payload): array
    {
        $context = $this->schemaContext($auth, $universeId);
        $worldId = (int) $context['world']['id'];
        $code = $this->code($payload['code'] ?? $payload['name'] ?? '');
        $schema = $this->fieldSchema($payload['fields'] ?? []);
        $now = $this->now();
        $this->db->table('compendium_entry_types')->insert([
            'world_id' => $worldId, 'code' => $code,
            'name' => $this->name($payload['name'] ?? '', 100, 'name'),
            'icon' => $this->code($payload['icon'] ?? 'book', 40), 'is_builtin' => 0,
            'field_schema_json' => json_encode($schema, JSON_UNESCAPED_UNICODE),
            'sort_order' => count($this->types($worldId)) + 1, 'created_at' => $now, 'updated_at' => $now,
        ]);
        return ['type' => $this->typePresentation($this->type((int) $this->db->insertID(), $worldId))];
    }

    public function updateType(int $universeId, int $typeId, array $auth, array $payload): array
    {
        $context = $this->schemaContext($auth, $universeId);
        $worldId = (int) $context['world']['id'];
        $type = $this->type($typeId, $worldId);
        $changes = [];
        if (isset($payload['name'])) $changes['name'] = $this->name($payload['name'], 100, 'name');
        if (isset($payload['icon'])) $changes['icon'] = $this->code($payload['icon'], 40);
        if (array_key_exists('fields', $payload)) {
            $next = $this->fieldSchema($payload['fields']);
            $used = (bool) $this->db->table('compendium_entry_versions')->where('type_id', $typeId)->countAllResults();
            if ($used) $this->assertCompatibleSchema($this->json($type['field_schema_json']), $next);
            $changes['field_schema_json'] = json_encode($next, JSON_UNESCAPED_UNICODE);
        }
        if (!$changes) throw new CampaignException('validation_failed', 'No type changes were supplied.', 422);
        $changes['updated_at'] = $this->now();
        $this->db->table('compendium_entry_types')->where('id', $typeId)->update($changes);
        return ['type' => $this->typePresentation($this->type($typeId, $worldId))];
    }

    public function deleteType(int $universeId, int $typeId, array $auth): array
    {
        $context = $this->schemaContext($auth, $universeId);
        $type = $this->type($typeId, (int) $context['world']['id']);
        if (!empty($type['is_builtin']) || $this->db->table('compendium_entry_versions')->where('type_id', $typeId)->countAllResults()) {
            throw new CampaignException('compendium_type_in_use', 'A built-in or used type cannot be deleted.', 409);
        }
        $this->db->table('compendium_entry_types')->where('id', $typeId)->delete();
        return ['deleted' => true, 'id' => $typeId];
    }

    public function updateCalendar(int $universeId, array $auth, array $payload): array
    {
        $context = $this->schemaContext($auth, $universeId);
        $worldId = (int) $context['world']['id'];
        $calendar = $this->calendarRow($worldId);
        $revision = $this->requiredRevision($payload);
        if ((int) $calendar['revision'] !== $revision) $this->conflict((int) $calendar['revision']);
        $months = $this->validateMonths($payload['months'] ?? []);
        $eras = $this->validateEras($payload['eras'] ?? []);
        if (!empty($calendar['structure_locked'])) {
            $current = $this->calendar($worldId);
            if ($this->calendarStructure($current['months'], $current['eras']) !== $this->calendarStructure($months, $eras)) {
                throw new CampaignException('calendar_structure_locked', 'Calendar structure is locked by published dates.', 409);
            }
        }
        $this->db->transBegin();
        try {
            $this->db->table('compendium_calendars')->set(['name' => $this->name($payload['name'] ?? $calendar['name'], 120, 'name'),
                'updated_at' => $this->now()])->set('revision', 'revision + 1', false)
                ->where('id', (int) $calendar['id'])->where('revision', $revision)->update();
            if ($this->db->affectedRows() !== 1) $this->conflict((int) $calendar['revision']);
            if (!empty($calendar['structure_locked'])) {
                $monthRows = $this->db->table('compendium_calendar_months')->where('calendar_id', (int) $calendar['id'])
                    ->orderBy('sort_order')->get()->getResultArray();
                foreach ($months as $i => $month) {
                    $this->db->table('compendium_calendar_months')->where('id', (int) $monthRows[$i]['id'])
                        ->update(['name' => $month['name']]);
                }
                $eraRows = $this->db->table('compendium_calendar_eras')->where('calendar_id', (int) $calendar['id'])
                    ->orderBy('sort_order')->get()->getResultArray();
                foreach ($eras as $i => $era) {
                    $this->db->table('compendium_calendar_eras')->where('id', (int) $eraRows[$i]['id'])
                        ->update(['name' => $era['name'], 'abbreviation' => $era['abbreviation']]);
                }
            } else {
                $this->db->table('compendium_calendar_months')->where('calendar_id', (int) $calendar['id'])->delete();
                foreach ($months as $i => $month) $this->db->table('compendium_calendar_months')->insert($month + ['calendar_id' => (int) $calendar['id'], 'sort_order' => $i]);
                $this->db->table('compendium_calendar_eras')->where('calendar_id', (int) $calendar['id'])->delete();
                foreach ($eras as $i => $era) $this->db->table('compendium_calendar_eras')->insert($era + ['calendar_id' => (int) $calendar['id'], 'sort_order' => $i]);
            }
            $this->commit('calendar_write_failed');
        } catch (\Throwable $error) { $this->db->transRollback(); throw $error; }
        return ['calendar' => $this->calendar($worldId)];
    }

    public function assignOwner(int $universeId, array $auth, array $payload): array
    {
        $context = $this->access->editorial($auth, $universeId, false);
        if (!$context['canAssignOwner']) throw new CampaignException('forbidden', 'Only an administrator may assign the owner.', 403);
        $userId = $this->idOrNull($payload['userId'] ?? null);
        if ($userId !== null && !$this->db->table('users')->where('id', $userId)->where('deleted_at', null)->countAllResults()) {
            throw new CampaignException('user_not_found', 'User was not found.', 404);
        }
        $this->db->table('compendium_worlds')->where('id', (int) $context['world']['id'])->update([
            'owner_user_id' => $userId, 'updated_at' => $this->now(),
        ]);
        if ($userId) $this->db->table('compendium_editors')->where('world_id', (int) $context['world']['id'])->where('user_id', $userId)->delete();
        return $this->overview($universeId, $auth);
    }

    public function addEditor(int $universeId, array $auth, array $payload): array
    {
        $context = $this->access->editorial($auth, $universeId, false);
        if (!$context['canManageEditors']) throw new CampaignException('forbidden', 'World owner access is required.', 403);
        $identity = trim((string) ($payload['identity'] ?? ''));
        $user = $this->db->table('users')->groupStart()->where('email', $identity)->orWhere('username', $identity)->groupEnd()
            ->where('deleted_at', null)->get()->getRowArray();
        if (!$user) throw new CampaignException('user_not_found', 'User was not found.', 404);
        if ((int) ($context['world']['owner_user_id'] ?? 0) === (int) $user['id']) return ['editors' => $this->editors((int) $context['world']['id'])];
        $this->db->table('compendium_editors')->ignore(true)->insert([
            'world_id' => (int) $context['world']['id'], 'user_id' => (int) $user['id'],
            'granted_by_user_id' => (int) $context['auth']['user_id'], 'created_at' => $this->now(),
        ]);
        return ['editors' => $this->editors((int) $context['world']['id'])];
    }

    public function removeEditor(int $universeId, int $userId, array $auth): array
    {
        $context = $this->access->editorial($auth, $universeId, false);
        if (!$context['canManageEditors']) throw new CampaignException('forbidden', 'World owner access is required.', 403);
        $this->db->table('compendium_editors')->where('world_id', (int) $context['world']['id'])->where('user_id', $userId)->delete();
        return ['editors' => $this->editors((int) $context['world']['id'])];
    }

    private function index(array $context, array $query, bool $draft): array
    {
        $this->bootstrap((int) $context['world']['id']);
        $page = max(1, min(100000, (int) ($query['page'] ?? 1)));
        $limit = max(1, min(100, (int) ($query['limit'] ?? 50)));
        $versionColumn = $draft ? 'draft_version_id' : 'published_version_id';
        $builder = $this->db->table('compendium_entries e')
            ->select('e.*, v.*, e.id AS entry_identity_id, e.revision AS entry_revision, e.status AS entry_status, e.slug AS entry_slug')
            ->join('compendium_entry_versions v', "v.id = e.{$versionColumn}", 'inner')
            ->where('e.world_id', (int) $context['world']['id']);
        if (!$draft) $builder->where('e.status', 'active')->where('e.deleted_at', null);
        elseif (($query['status'] ?? '') !== 'all') $builder->where('e.status', $query['status'] ?? 'active');
        if (!$context['canSeeGm']) $builder->where('v.visibility', 'players');
        if (!empty($query['type'])) $builder->where('v.type_id', (int) $query['type']);
        if (!empty($query['parent'])) $builder->where('v.parent_entry_id', (int) $query['parent']);
        if (!empty($query['visibility']) && $context['canSeeGm'] && in_array($query['visibility'], ['players', 'gm_only'], true)) {
            $builder->where('v.visibility', $query['visibility']);
        }
        if (!empty($query['dated'])) $builder->where('v.start_ordinal IS NOT NULL', null, false);
        if (isset($query['from'])) $builder->where('COALESCE(v.end_ordinal, v.start_ordinal) >=', (int) $query['from']);
        if (isset($query['to'])) $builder->where('v.start_ordinal <=', (int) $query['to']);
        if (!empty($query['tag'])) {
            $builder->join('compendium_version_tags vt', 'vt.version_id = v.id', 'inner')->where('vt.tag_id', (int) $query['tag']);
        }
        $q = trim((string) ($query['q'] ?? ''));
        if ($q !== '') {
            $builder->groupStart()->like('v.public_search_text', $q);
            if ($context['canSeeGm']) $builder->orLike('v.gm_search_text', $q);
            $builder->groupEnd();
        }
        if (($query['sort'] ?? '') === 'timeline') $builder->orderBy('v.start_ordinal', 'ASC')->orderBy('v.title', 'ASC');
        else $builder->orderBy('v.title', 'ASC');
        $rows = $builder->limit($limit, ($page - 1) * $limit)->get()->getResultArray();
        return [
            'items' => array_map(fn (array $row): array => $this->presentRow($context, $row, $draft, false), $rows),
            'page' => $page, 'limit' => $limit, 'hasMore' => count($rows) === $limit,
            'capabilities' => $this->capabilities($context),
        ];
    }

    private function presentEntry(array $context, int $entryId, bool $draft): array
    {
        $entry = $this->entry($context, $entryId);
        if (!$draft && ($entry['status'] !== 'active' || empty($entry['published_version_id']))) {
            throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        }
        $versionId = (int) ($entry[$draft ? 'draft_version_id' : 'published_version_id'] ?? 0);
        if ($versionId < 1) throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        $row = $this->version($versionId) + [
            'entry_identity_id' => (int) $entry['id'], 'entry_revision' => (int) $entry['revision'],
            'entry_status' => $entry['status'], 'entry_slug' => $entry['slug'],
        ];
        if (!$context['canSeeGm'] && $row['visibility'] !== 'players') throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        return $this->presentRow($context, $row, $draft, true);
    }

    private function presentRow(array $context, array $row, bool $draft, bool $detail): array
    {
        $versionId = (int) $row['id'];
        $result = [
            'id' => (int) $row['entry_identity_id'], 'versionId' => $versionId,
            'versionNumber' => $row['version_number'] === null ? null : (int) $row['version_number'],
            'universeId' => (int) $context['world']['universe_id'], 'slug' => (string) $row['entry_slug'],
            'typeId' => (int) $row['type_id'], 'parentEntryId' => $row['parent_entry_id'] === null ? null : (int) $row['parent_entry_id'],
            'title' => (string) $row['title'], 'aliases' => array_values($this->json($row['aliases_json'])),
            'excerpt' => $row['excerpt'], 'visibility' => (string) $row['visibility'],
            'chronology' => $this->jsonOrNull($row['chronology_json']),
            'startOrdinal' => $row['start_ordinal'] === null ? null : (int) $row['start_ordinal'],
            'endOrdinal' => $row['end_ordinal'] === null ? null : (int) $row['end_ordinal'],
            'tags' => $this->versionTags($versionId), 'status' => (string) $row['entry_status'],
            'revision' => (int) $row['entry_revision'], 'state' => (string) $row['state'],
            'capabilities' => $this->capabilities($context),
        ];
        if ($detail) {
            $result['publicContent'] = $this->json($row['public_content_json']);
            $result['publicFields'] = (object) $this->json($row['public_fields_json']);
            $result['relations'] = $this->relations($context, $versionId, $draft);
            $result['assets'] = $this->versionAssets($context, $versionId);
            $result['mentions'] = $this->mentions($context, $row, $draft);
            $result['backlinks'] = $this->backlinks($context, (int) $row['entry_identity_id'], $draft);
            if ($context['canSeeGm']) {
                $result['gmContent'] = $this->json($row['gm_content_json']);
                $result['gmFields'] = (object) $this->json($row['gm_fields_json']);
                $result['statBlocks'] = array_values($this->json($row['stat_blocks_json']));
            }
        }
        return $result;
    }

    private function entryPayload(array $context, array $payload, ?array $existing): array
    {
        $worldId = (int) $context['world']['id'];
        $typeId = isset($payload['typeId']) ? (int) $payload['typeId'] : (int) ($existing['type_id'] ?? 0);
        $type = $this->type($typeId, $worldId);
        $title = array_key_exists('title', $payload) ? $this->name($payload['title'], 180, 'title') : (string) ($existing['title'] ?? '');
        $visibility = (string) ($payload['visibility'] ?? $existing['visibility'] ?? 'players');
        if (!in_array($visibility, ['players', 'gm_only'], true)) throw new CampaignException('validation_failed', 'Visibility is invalid.', 422, ['visibility' => 'Use players or gm_only.']);
        $public = $this->documents->validate($payload['publicContent'] ?? ($existing ? $this->json($existing['public_content_json']) : self::EMPTY_DOCUMENT));
        if (!$public['valid']) throw new CampaignException('validation_failed', 'Public content is invalid.', 422, $public['errors']);
        $gm = $this->documents->validate($payload['gmContent'] ?? ($existing ? $this->json($existing['gm_content_json']) : self::EMPTY_DOCUMENT));
        if (!$gm['valid']) throw new CampaignException('validation_failed', 'GM content is invalid.', 422, $gm['errors']);
        $schema = $this->json($type['field_schema_json']);
        $publicFields = $payload['publicFields'] ?? ($existing ? $this->json($existing['public_fields_json']) : []);
        $gmFields = $payload['gmFields'] ?? ($existing ? $this->json($existing['gm_fields_json']) : []);
        $this->validateFields($schema, $publicFields, $gmFields, $worldId, (int) ($existing['entry_id'] ?? 0));
        $chronology = array_key_exists('chronology', $payload)
            ? $this->chronology($worldId, $payload['chronology'])
            : ($existing && !empty($existing['chronology_json']) ? [
                'value' => $this->jsonOrNull($existing['chronology_json']),
                'start' => $existing['start_ordinal'] === null ? null : (int) $existing['start_ordinal'],
                'end' => $existing['end_ordinal'] === null ? null : (int) $existing['end_ordinal'],
            ] : null);
        $parentId = array_key_exists('parentEntryId', $payload) ? $this->idOrNull($payload['parentEntryId']) : ($existing['parent_entry_id'] ?? null);
        if ($parentId !== null) $this->assertParent($worldId, (int) ($existing['entry_id'] ?? 0), $parentId);
        $aliases = array_key_exists('aliases', $payload) ? $this->stringList($payload['aliases'], 20, 100, 'aliases') : ($existing ? $this->json($existing['aliases_json']) : []);
        $excerpt = array_key_exists('excerpt', $payload) ? $this->optionalName($payload['excerpt'], 500) : ($existing['excerpt'] ?? null);
        $statBlocks = array_key_exists('statBlocks', $payload) ? $this->statBlocks($payload['statBlocks'], (string) $type['code']) : ($existing ? $this->json($existing['stat_blocks_json']) : []);
        $tags = array_key_exists('tagIds', $payload) ? $this->ownedIds('compendium_tags', $worldId, $payload['tagIds']) : ($existing ? array_column($this->versionTags((int) $existing['id']), 'id') : []);
        $relations = array_key_exists('relations', $payload) ? $this->relationPayload($worldId, (int) ($existing['entry_id'] ?? 0), $payload['relations']) : ($existing ? $this->rawRelations((int) $existing['id']) : []);
        $publicSearch = trim(implode(' ', array_filter([$title, implode(' ', $aliases), $excerpt, $public['text'], $this->flatten($publicFields)])));
        $gmSearch = trim(implode(' ', array_filter([$gm['text'], $this->flatten($gmFields)])));
        return [
            'version' => [
                'version_number' => null, 'state' => 'draft', 'type_id' => $typeId,
                'parent_entry_id' => $parentId, 'title' => $title,
                'aliases_json' => json_encode($aliases, JSON_UNESCAPED_UNICODE), 'excerpt' => $excerpt,
                'visibility' => $visibility, 'public_content_json' => $public['json'], 'gm_content_json' => $gm['json'],
                'public_fields_json' => json_encode($publicFields, JSON_UNESCAPED_UNICODE),
                'gm_fields_json' => json_encode($gmFields, JSON_UNESCAPED_UNICODE),
                'chronology_json' => $chronology ? json_encode($chronology['value'], JSON_UNESCAPED_UNICODE) : null,
                'start_ordinal' => $chronology['start'] ?? null, 'end_ordinal' => $chronology['end'] ?? null,
                'stat_blocks_json' => json_encode($statBlocks, JSON_UNESCAPED_UNICODE),
                'public_search_text' => mb_substr($publicSearch, 0, 20000),
                'gm_search_text' => mb_substr($gmSearch, 0, 20000), 'published_at' => null,
            ],
            'tagIds' => $tags, 'relations' => $relations,
            'publicAssetIds' => $public['assetIds'], 'gmAssetIds' => $gm['assetIds'],
            'publicMentionIds' => $public['mentionIds'], 'gmMentionIds' => $gm['mentionIds'],
        ];
    }

    private function insertVersion(array $context, array $version, array $associations): int
    {
        $now = $this->now();
        $version['created_by_user_id'] = (int) $context['auth']['user_id'];
        $version['created_at'] = $now; $version['updated_at'] = $now;
        $this->db->table('compendium_entry_versions')->insert($version);
        $id = (int) $this->db->insertID();
        if ($id < 1) throw new CampaignException('compendium_write_failed', 'Entry version could not be saved.', 500);
        $this->replaceAssociations($context, $id, $associations);
        return $id;
    }

    private function replaceAssociations(array $context, int $versionId, array $data): void
    {
        $this->db->table('compendium_version_tags')->where('version_id', $versionId)->delete();
        foreach ($data['tagIds'] as $tagId) $this->db->table('compendium_version_tags')->insert(['version_id' => $versionId, 'tag_id' => $tagId]);
        $this->db->table('compendium_version_relations')->where('version_id', $versionId)->delete();
        foreach ($data['relations'] as $i => $relation) $this->db->table('compendium_version_relations')->insert([
            'version_id' => $versionId, 'target_entry_id' => $relation['targetEntryId'],
            'label' => $relation['label'], 'audience' => $relation['audience'], 'sort_order' => $i,
        ]);
        $this->db->table('compendium_asset_references')->where('version_id', $versionId)->delete();
        foreach ([['publicAssetIds', 'public'], ['gmAssetIds', 'gm']] as [$key, $audience]) {
            foreach ($data[$key] as $assetId) {
                $this->ownedRow('compendium_assets', $assetId, (int) $context['world']['id'], 'compendium_asset_not_found');
                $this->db->table('compendium_asset_references')->insert(['asset_id' => $assetId, 'version_id' => $versionId, 'audience' => $audience]);
            }
        }
    }

    private function cloneVersion(array $context, int $entryId, int $sourceId): int
    {
        $source = $this->version($sourceId);
        $copy = $source;
        foreach (['id', 'created_at', 'updated_at'] as $field) unset($copy[$field]);
        $copy['entry_id'] = $entryId; $copy['state'] = 'draft'; $copy['version_number'] = null;
        $copy['published_at'] = null;
        $associations = [
            'tagIds' => array_column($this->versionTags($sourceId), 'id'),
            'relations' => $this->rawRelations($sourceId),
            'publicAssetIds' => $this->assetIds($sourceId, 'public'),
            'gmAssetIds' => $this->assetIds($sourceId, 'gm'),
        ];
        return $this->insertVersion($context, $copy, $associations);
    }

    private function validatePublish(array $context, int $entryId, array $draft): void
    {
        $relations = $this->rawRelations((int) $draft['id']);
        $publicMentionIds = $this->documents->validate($this->json($draft['public_content_json']))['mentionIds'] ?? [];
        foreach (array_merge($publicMentionIds, array_column(array_filter($relations, static fn ($r) => $r['audience'] === 'public'), 'targetEntryId')) as $targetId) {
            $target = $this->db->table('compendium_entries e')->select('v.visibility')
                ->join('compendium_entry_versions v', 'v.id = e.published_version_id', 'inner')
                ->where('e.id', (int) $targetId)->where('e.world_id', (int) $context['world']['id'])
                ->where('e.status', 'active')->get()->getRowArray();
            if (!$target || $target['visibility'] !== 'players') throw new CampaignException('validation_failed', 'Public content links to an unpublished or GM-only entry.', 422, ['relations' => 'Publish and reveal public targets first.']);
        }
        if ((int) ($draft['parent_entry_id'] ?? 0) === $entryId) throw new CampaignException('validation_failed', 'An entry cannot be its own parent.', 422);
    }

    private function relations(array $context, int $versionId, bool $draft): array
    {
        $targetVersion = $draft ? 'draft_version_id' : 'published_version_id';
        $builder = $this->db->table('compendium_version_relations r')->select('r.*, e.slug, v.title, v.visibility')
            ->join('compendium_entries e', 'e.id = r.target_entry_id', 'inner')
            ->join('compendium_entry_versions v', "v.id = e.{$targetVersion}", 'inner')
            ->where('r.version_id', $versionId);
        if (!$draft) $builder->where('e.status', 'active')->where('e.deleted_at', null);
        if (!$context['canSeeGm']) $builder->where('r.audience', 'public')->where('v.visibility', 'players');
        return array_map(static fn (array $row): array => [
            'targetEntryId' => (int) $row['target_entry_id'], 'slug' => (string) $row['slug'],
            'title' => (string) $row['title'], 'label' => (string) $row['label'], 'audience' => (string) $row['audience'],
        ], $builder->orderBy('r.sort_order', 'ASC')->get()->getResultArray());
    }

    private function mentions(array $context, array $version, bool $draft): array
    {
        $ids = $this->documents->validate($this->json($version['public_content_json']))['mentionIds'] ?? [];
        if (!empty($context['canSeeGm'])) {
            $ids = array_merge($ids, $this->documents->validate($this->json($version['gm_content_json']))['mentionIds'] ?? []);
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));
        if (!$ids) return [];
        $targetVersion = $draft ? 'draft_version_id' : 'published_version_id';
        $builder = $this->db->table('compendium_entries e')->select('e.id, e.slug, v.title, v.visibility')
            ->join('compendium_entry_versions v', "v.id=e.{$targetVersion}", 'inner')
            ->where('e.world_id', (int) $context['world']['id'])->whereIn('e.id', $ids);
        if (!$draft) $builder->where('e.status', 'active')->where('e.deleted_at', null);
        if (empty($context['canSeeGm'])) $builder->where('v.visibility', 'players');
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'slug' => (string) $row['slug'],
            'title' => (string) $row['title'], 'visibility' => (string) $row['visibility'],
        ], $builder->get()->getResultArray());
    }

    private function backlinks(array $context, int $entryId, bool $draft): array
    {
        $versionColumn = $draft ? 'draft_version_id' : 'published_version_id';
        $builder = $this->db->table('compendium_version_relations r')
            ->select('source.id, source.slug, source_version.title, r.label, r.audience')
            ->join('compendium_entries source', "source.{$versionColumn} = r.version_id", 'inner')
            ->join('compendium_entry_versions source_version', "source_version.id = source.{$versionColumn}", 'inner')
            ->where('r.target_entry_id', $entryId)->where('source.world_id', (int) $context['world']['id'])
            ->where('source.status', 'active')->where('source.deleted_at', null);
        if (empty($context['canSeeGm'])) $builder->where('r.audience', 'public')->where('source_version.visibility', 'players');
        return array_map(static fn (array $row): array => [
            'sourceEntryId' => (int) $row['id'], 'slug' => (string) $row['slug'],
            'title' => (string) $row['title'], 'label' => (string) $row['label'],
            'audience' => (string) $row['audience'],
        ], $builder->orderBy('source_version.title')->get()->getResultArray());
    }

    private function editorialContext(array $auth, int $universeId): array
    {
        $context = $this->access->editorial($auth, $universeId, true);
        $this->bootstrap((int) $context['world']['id']);
        return $context;
    }

    private function schemaContext(array $auth, int $universeId): array
    {
        $context = $this->editorialContext($auth, $universeId);
        if (!$context['canManageSchema']) throw new CampaignException('forbidden', 'World owner access is required.', 403);
        return $context;
    }

    private function bootstrap(int $worldId): void
    {
        $now = $this->now();
        if (!$this->db->table('compendium_calendars')->where('world_id', $worldId)->countAllResults()) {
            $this->db->table('compendium_calendars')->ignore(true)->insert([
                'world_id' => $worldId, 'name' => 'Calendar', 'structure_locked' => 0,
                'revision' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        foreach (self::BUILTIN_TYPES as $order => [$code, $name, $icon]) {
            $this->db->table('compendium_entry_types')->ignore(true)->insert([
                'world_id' => $worldId, 'code' => $code, 'name' => $name, 'icon' => $icon,
                'is_builtin' => 1, 'field_schema_json' => '[]', 'sort_order' => $order,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function calendar(int $worldId): array
    {
        $row = $this->calendarRow($worldId);
        return [
            'id' => (int) $row['id'], 'name' => (string) $row['name'],
            'structureLocked' => !empty($row['structure_locked']), 'revision' => (int) $row['revision'],
            'months' => array_map(static fn ($item) => ['id' => (int) $item['id'], 'name' => $item['name'], 'days' => (int) $item['days']],
                $this->db->table('compendium_calendar_months')->where('calendar_id', $row['id'])->orderBy('sort_order')->get()->getResultArray()),
            'eras' => array_map(static fn ($item) => ['id' => (int) $item['id'], 'name' => $item['name'],
                'abbreviation' => $item['abbreviation'], 'epochOrdinal' => (int) $item['epoch_ordinal'], 'direction' => (int) $item['direction']],
                $this->db->table('compendium_calendar_eras')->where('calendar_id', $row['id'])->orderBy('sort_order')->get()->getResultArray()),
        ];
    }

    private function calendarRow(int $worldId): array
    {
        $this->bootstrap($worldId);
        return $this->db->table('compendium_calendars')->where('world_id', $worldId)->get()->getRowArray();
    }

    private function chronology(int $worldId, $value): ?array
    {
        if ($value === null || $value === [] || $value === '') return null;
        if (!is_array($value)) throw new CampaignException('validation_failed', 'Chronology is invalid.', 422);
        $precision = (string) ($value['precision'] ?? 'day');
        if (!in_array($precision, ['day', 'month', 'year', 'range'], true)) throw new CampaignException('validation_failed', 'Date precision is invalid.', 422);
        $calendar = $this->calendar($worldId);
        if (!$calendar['months'] || !$calendar['eras']) throw new CampaignException('calendar_unconfigured', 'Configure calendar months and eras first.', 409);
        $start = CompendiumCalendar::ordinal((array) ($value['start'] ?? []), $calendar['months'], $calendar['eras']);
        if (!$start['valid']) throw new CampaignException('validation_failed', $start['error'], 422, ['chronology' => $start['error']]);
        $endOrdinal = $start['ordinal'];
        if ($precision === 'range') {
            $end = CompendiumCalendar::ordinal((array) ($value['end'] ?? []), $calendar['months'], $calendar['eras']);
            if (!$end['valid'] || $end['ordinal'] < $start['ordinal']) throw new CampaignException('validation_failed', 'Chronology range is invalid.', 422);
            $endOrdinal = $end['ordinal'];
        }
        return ['value' => ['calendarId' => $calendar['id'], 'precision' => $precision,
            'start' => $value['start'], 'end' => $precision === 'range' ? $value['end'] : null],
            'start' => $start['ordinal'], 'end' => $endOrdinal];
    }

    private function types(int $worldId): array
    {
        return array_map(fn ($row) => $this->typePresentation($row), $this->db->table('compendium_entry_types')
            ->where('world_id', $worldId)->orderBy('sort_order')->orderBy('name')->get()->getResultArray());
    }

    private function typePresentation(array $row): array
    {
        return ['id' => (int) $row['id'], 'code' => $row['code'], 'name' => $row['name'],
            'icon' => $row['icon'], 'builtin' => !empty($row['is_builtin']), 'fields' => array_values($this->json($row['field_schema_json']))];
    }

    private function tags(int $worldId): array
    {
        return array_map(static fn ($row) => ['id' => (int) $row['id'], 'name' => $row['name'], 'color' => $row['color']],
            $this->db->table('compendium_tags')->where('world_id', $worldId)->orderBy('name')->get()->getResultArray());
    }

    private function editors(int $worldId): array
    {
        return array_map(static fn ($row) => ['userId' => (int) $row['user_id'], 'username' => $row['username'], 'email' => $row['email']],
            $this->db->table('compendium_editors e')->select('e.user_id, u.username, u.email')
                ->join('users u', 'u.id = e.user_id', 'inner')->where('e.world_id', $worldId)->orderBy('u.username')->get()->getResultArray());
    }

    private function historyRows(array $context, int $entryId): array
    {
        $this->entry($context, $entryId);
        return array_map(static fn ($row) => ['versionId' => (int) $row['id'], 'versionNumber' => (int) $row['version_number'],
            'title' => $row['title'], 'publishedAt' => $row['published_at']],
            $this->db->table('compendium_entry_versions')->where('entry_id', $entryId)->where('state', 'published')
                ->orderBy('version_number', 'DESC')->get()->getResultArray());
    }

    private function capabilities(array $context): array
    {
        return ['canSeeGm' => (bool) $context['canSeeGm'], 'canEdit' => (bool) $context['canEdit'],
            'canManageSchema' => (bool) $context['canManageSchema'], 'canManageEditors' => (bool) $context['canManageEditors'],
            'canAssignOwner' => (bool) $context['canAssignOwner'], 'canMaterialize' => (bool) $context['canMaterialize']];
    }

    private function entry(array $context, int $entryId): array
    {
        $entry = $this->db->table('compendium_entries')->where('id', $entryId)
            ->where('world_id', (int) $context['world']['id'])->get()->getRowArray();
        if (!$entry) throw new CampaignException('compendium_entry_not_found', 'Entry was not found.', 404);
        return $entry;
    }

    private function version(int $id): array
    {
        $row = $this->db->table('compendium_entry_versions')->where('id', $id)->get()->getRowArray();
        if (!$row) throw new CampaignException('compendium_version_not_found', 'Version was not found.', 404);
        return $row;
    }

    private function type(int $id, int $worldId): array
    {
        $row = $this->db->table('compendium_entry_types')->where('id', $id)->where('world_id', $worldId)->get()->getRowArray();
        if (!$row) throw new CampaignException('compendium_type_not_found', 'Entry type was not found.', 404);
        return $row;
    }

    private function versionTags(int $versionId): array
    {
        return array_map(static fn ($row) => ['id' => (int) $row['id'], 'name' => $row['name'], 'color' => $row['color']],
            $this->db->table('compendium_tags t')->select('t.id,t.name,t.color')->join('compendium_version_tags vt', 'vt.tag_id=t.id', 'inner')
                ->where('vt.version_id', $versionId)->orderBy('t.name')->get()->getResultArray());
    }

    private function rawRelations(int $versionId): array
    {
        return array_map(static fn ($row) => ['targetEntryId' => (int) $row['target_entry_id'], 'label' => $row['label'], 'audience' => $row['audience']],
            $this->db->table('compendium_version_relations')->where('version_id', $versionId)->orderBy('sort_order')->get()->getResultArray());
    }

    private function assetIds(int $versionId, string $audience): array
    {
        return array_map('intval', array_column($this->db->table('compendium_asset_references')->select('asset_id')
            ->where('version_id', $versionId)->where('audience', $audience)->get()->getResultArray(), 'asset_id'));
    }

    private function versionAssets(array $context, int $versionId): array
    {
        $builder = $this->db->table('compendium_asset_references r')
            ->select('a.id, a.original_name, a.mime_type, a.byte_size, r.audience')
            ->join('compendium_assets a', 'a.id = r.asset_id', 'inner')
            ->where('r.version_id', $versionId)->where('a.deleted_at', null);
        if (empty($context['canSeeGm'])) $builder->where('r.audience', 'public');
        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'], 'name' => (string) $row['original_name'],
            'mimeType' => (string) $row['mime_type'], 'byteSize' => (int) $row['byte_size'],
            'audience' => (string) $row['audience'],
        ], $builder->orderBy('a.original_name')->get()->getResultArray());
    }

    private function relationPayload(int $worldId, int $entryId, $value): array
    {
        if (!is_array($value) || count($value) > 50) throw new CampaignException('validation_failed', 'Use at most 50 relations.', 422);
        $result = [];
        foreach ($value as $relation) {
            if (!is_array($relation)) throw new CampaignException('validation_failed', 'Relation is invalid.', 422);
            $target = (int) ($relation['targetEntryId'] ?? 0);
            if ($target < 1 || ($entryId > 0 && $target === $entryId)) throw new CampaignException('validation_failed', 'Relation target is invalid.', 422);
            $this->ownedRow('compendium_entries', $target, $worldId, 'compendium_entry_not_found');
            $audience = (string) ($relation['audience'] ?? 'public');
            if (!in_array($audience, ['public', 'gm'], true)) throw new CampaignException('validation_failed', 'Relation audience is invalid.', 422);
            $result[$target . ':' . $audience] = ['targetEntryId' => $target,
                'label' => $this->name($relation['label'] ?? 'Related', 180, 'relations'), 'audience' => $audience];
        }
        return array_values($result);
    }

    private function assertParent(int $worldId, int $entryId, int $parentId): void
    {
        if ($entryId > 0 && $entryId === $parentId) throw new CampaignException('validation_failed', 'An entry cannot be its own parent.', 422);
        $this->ownedRow('compendium_entries', $parentId, $worldId, 'compendium_entry_not_found');
        $seen = [$entryId => true]; $current = $parentId;
        for ($depth = 0; $depth < 100 && $current; $depth++) {
            if (isset($seen[$current])) throw new CampaignException('compendium_hierarchy_cycle', 'Entry hierarchy cannot contain a cycle.', 422);
            $seen[$current] = true;
            $row = $this->db->table('compendium_entries e')->select('v.parent_entry_id')
                ->join('compendium_entry_versions v', 'v.id=e.draft_version_id', 'inner')->where('e.id', $current)->get()->getRowArray();
            $current = (int) ($row['parent_entry_id'] ?? 0);
        }
    }

    private function statBlocks($value, string $typeCode): array
    {
        if ($value === null) return [];
        if (!is_array($value) || count($value) > 20) throw new CampaignException('validation_failed', 'Stat blocks are invalid.', 422);
        if ($typeCode !== 'creature' && $value) throw new CampaignException('validation_failed', 'Only creature entries may contain stat blocks.', 422);
        $result = [];
        foreach ($value as $block) {
            if (!is_array($block) || (int) ($block['systemId'] ?? 0) < 1 || !is_array($block['data'] ?? null)) throw new CampaignException('validation_failed', 'Stat block is invalid.', 422);
            $encoded = json_encode($block['data'], JSON_UNESCAPED_UNICODE);
            if (!is_string($encoded) || strlen($encoded) > 262144) throw new CampaignException('validation_failed', 'Stat block data is too large.', 422);
            $systemId = (int) $block['systemId'];
            $assets = is_array($block['assetPublicIds'] ?? null) ? $block['assetPublicIds'] : [];
            if (array_diff(array_keys($assets), ['avatar', 'portrait', 'token', 'fullbody'])) {
                throw new CampaignException('validation_failed', 'Stat block assets contain an unsupported type.', 422);
            }
            foreach ($assets as $assetType => $publicId) {
                if (!is_string($publicId) || trim($publicId) === '' || mb_strlen($publicId) > 255) {
                    throw new CampaignException('validation_failed', 'Stat block asset public ID is invalid.', 422, [$assetType => 'Use a non-empty public ID.']);
                }
                $assets[$assetType] = trim($publicId);
            }
            $result[$systemId] = ['systemId' => $systemId, 'data' => $block['data'],
                'token' => is_array($block['token'] ?? null) ? $block['token'] : [], 'assetPublicIds' => $assets];
        }
        return array_values($result);
    }

    private function fieldSchema($fields): array
    {
        if (!is_array($fields) || count($fields) > 50) throw new CampaignException('validation_failed', 'Type fields are invalid.', 422);
        $result = [];
        foreach ($fields as $field) {
            if (!is_array($field)) throw new CampaignException('validation_failed', 'Type field is invalid.', 422);
            $key = $this->code($field['key'] ?? ''); $type = (string) ($field['type'] ?? '');
            $audience = (string) ($field['audience'] ?? 'public');
            if (!in_array($type, self::FIELD_TYPES, true) || !in_array($audience, ['public', 'gm'], true)) throw new CampaignException('validation_failed', 'Field type or audience is invalid.', 422);
            $options = in_array($type, ['select', 'multiselect'], true) ? $this->stringList($field['options'] ?? [], 100, 100, 'options') : [];
            $result[$key] = ['key' => $key, 'label' => $this->name($field['label'] ?? $key, 100, 'label'),
                'type' => $type, 'audience' => $audience, 'required' => !empty($field['required']),
                'deprecated' => !empty($field['deprecated']), 'options' => $options];
        }
        return array_values($result);
    }

    private function validateFields(array $schema, $public, $gm, int $worldId, int $entryId): void
    {
        if (!is_array($public) || !is_array($gm)) throw new CampaignException('validation_failed', 'Entry fields must be objects.', 422);
        $known = [];
        foreach ($schema as $field) {
            $key = (string) $field['key']; $known[$key] = true;
            $source = $field['audience'] === 'gm' ? $gm : $public;
            $missing = !array_key_exists($key, $source) || $source[$key] === '' || $source[$key] === null
                || (is_array($source[$key]) && !$source[$key]);
            if (!empty($field['required']) && empty($field['deprecated']) && $missing) {
                throw new CampaignException('validation_failed', 'A required field is missing.', 422, [$key => 'This field is required.']);
            }
            if (array_key_exists($key, $source)) $this->validateFieldValue($field, $source[$key], $worldId, $entryId);
            $wrong = $field['audience'] === 'gm' ? $public : $gm;
            if (array_key_exists($key, $wrong)) throw new CampaignException('validation_failed', 'Field is in the wrong audience section.', 422, [$key => 'Field audience mismatch.']);
        }
        foreach (array_merge(array_keys($public), array_keys($gm)) as $key) if (!isset($known[$key])) throw new CampaignException('validation_failed', 'Unknown entry field.', 422, [$key => 'Field is not defined by the type.']);
    }

    private function validateFieldValue(array $field, $value, int $worldId, int $entryId): void
    {
        $valid = true;
        if ($field['type'] === 'text') $valid = is_string($value) && mb_strlen($value) <= 2000;
        elseif ($field['type'] === 'number') $valid = is_numeric($value) && is_finite((float) $value);
        elseif ($field['type'] === 'boolean') $valid = is_bool($value);
        elseif ($field['type'] === 'select') $valid = is_string($value) && in_array($value, $field['options'], true);
        elseif ($field['type'] === 'multiselect') $valid = is_array($value) && !array_diff($value, $field['options']);
        elseif ($field['type'] === 'relation') {
            $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false;
            if ($valid && $entryId > 0 && (int) $value === $entryId) $valid = false;
            if ($valid) $this->ownedRow('compendium_entries', (int) $value, $worldId, 'compendium_entry_not_found');
        } elseif ($field['type'] === 'date') {
            $valid = is_array($value);
            if ($valid) $this->chronology($worldId, isset($value['precision']) ? $value : ['precision' => 'day', 'start' => $value]);
        }
        if (!$valid) throw new CampaignException('validation_failed', 'Entry field value is invalid.', 422, [$field['key'] => 'Value does not match the field type.']);
    }

    private function assertCompatibleSchema(array $old, array $next): void
    {
        $nextByKey = []; foreach ($next as $field) $nextByKey[$field['key']] = $field;
        foreach ($old as $field) {
            $candidate = $nextByKey[$field['key']] ?? null;
            if (!$candidate || $candidate['type'] !== $field['type'] || $candidate['audience'] !== $field['audience']) {
                throw new CampaignException('compendium_schema_in_use', 'Used field keys, types and audiences are immutable.', 409);
            }
        }
    }

    private function validateMonths($value): array
    {
        if (!is_array($value) || !$value || count($value) > 36) throw new CampaignException('validation_failed', 'Calendar requires 1-36 months.', 422);
        return array_map(function ($row) {
            $days = filter_var($row['days'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
            if ($days === false) throw new CampaignException('validation_failed', 'Month length must be between 1 and 1000 days.', 422);
            return ['name' => $this->name($row['name'] ?? '', 80, 'months'), 'days' => (int) $days];
        }, $value);
    }

    private function validateEras($value): array
    {
        if (!is_array($value) || !$value || count($value) > 50) throw new CampaignException('validation_failed', 'Calendar requires 1-50 eras.', 422);
        return array_map(fn ($row) => ['name' => $this->name($row['name'] ?? '', 100, 'eras'),
            'abbreviation' => $this->name($row['abbreviation'] ?? '', 20, 'eras'),
            'epoch_ordinal' => (int) ($row['epochOrdinal'] ?? 0), 'direction' => (int) ($row['direction'] ?? 1) === -1 ? -1 : 1], $value);
    }

    private function calendarStructure(array $months, array $eras): string
    {
        return json_encode([array_map(fn ($m) => [(int) ($m['days'] ?? 0)], $months),
            array_map(fn ($e) => [(int) ($e['epochOrdinal'] ?? $e['epoch_ordinal'] ?? 0), (int) ($e['direction'] ?? 1)], $eras)]);
    }

    private function ownedIds(string $table, int $worldId, $values): array
    {
        if (!is_array($values) || count($values) > 100) throw new CampaignException('validation_failed', 'Identifier list is invalid.', 422);
        $ids = array_values(array_unique(array_map('intval', $values)));
        foreach ($ids as $id) $this->ownedRow($table, $id, $worldId, 'compendium_resource_not_found');
        return $ids;
    }

    private function ownedRow(string $table, int $id, int $worldId, string $code): array
    {
        $row = $this->db->table($table)->where('id', $id)->where('world_id', $worldId)->get()->getRowArray();
        if (!$row) throw new CampaignException($code, 'Resource was not found.', 404);
        return $row;
    }

    private function uniqueSlug(int $worldId, string $source): string
    {
        $base = trim(strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $source) ?: $source)), '-');
        $base = mb_substr($base ?: 'entry', 0, 150); $slug = $base; $suffix = 2;
        while ($this->db->table('compendium_entries')->where('world_id', $worldId)->where('slug', $slug)->countAllResults()) $slug = $base . '-' . $suffix++;
        return $slug;
    }

    private function requiredRevision(array $payload): int
    {
        $value = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) throw new CampaignException('validation_failed', 'Revision is required.', 422, ['revision' => 'Use a positive integer.']);
        return (int) $value;
    }

    private function conflictFor(int $entryId): void
    {
        $row = $this->db->table('compendium_entries')->select('revision')->where('id', $entryId)->get()->getRowArray();
        $this->conflict((int) ($row['revision'] ?? 1));
    }

    private function conflict(int $revision): void
    {
        throw new CampaignException('revision_conflict', 'Compendium data changed since it was loaded.', 409, ['currentRevision' => $revision]);
    }

    private function commit(string $code): void
    {
        if ($this->db->transStatus() === false) { $this->db->transRollback(); throw new CampaignException($code, 'Compendium data could not be saved.', 500); }
        $this->db->transCommit();
    }

    private function json($value): array
    {
        if (is_array($value)) return $value;
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function jsonOrNull($value): ?array
    {
        if ($value === null || $value === '') return null;
        return $this->json($value);
    }

    private function stringList($value, int $count, int $length, string $field): array
    {
        if (!is_array($value) || count($value) > $count) throw new CampaignException('validation_failed', 'List is invalid.', 422, [$field => 'Too many values.']);
        return array_values(array_unique(array_filter(array_map(function ($item) use ($length, $field) {
            return $this->name($item, $length, $field);
        }, $value))));
    }

    private function flatten($value): string
    {
        if (is_scalar($value)) return (string) $value;
        if (!is_array($value)) return '';
        return implode(' ', array_map(fn ($child) => $this->flatten($child), $value));
    }

    private function name($value, int $max, string $field): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) > $max) throw new CampaignException('validation_failed', 'Text value is invalid.', 422, [$field => "Use 1-{$max} characters."]);
        return $name;
    }

    private function optionalName($value, int $max): ?string
    {
        $value = trim((string) $value);
        if ($value === '') return null;
        if (mb_strlen($value) > $max) throw new CampaignException('validation_failed', 'Text value is too long.', 422);
        return $value;
    }

    private function code($value, int $max = 64): string
    {
        $code = strtolower(trim((string) preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string) $value), '_'));
        if ($code === '' || strlen($code) > $max) throw new CampaignException('validation_failed', 'Code is invalid.', 422);
        return $code;
    }

    private function color($value): ?string
    {
        if ($value === null || $value === '') return null;
        if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $value)) throw new CampaignException('validation_failed', 'Color is invalid.', 422);
        return strtolower((string) $value);
    }

    private function idOrNull($value): ?int
    {
        if ($value === null || $value === '') return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CampaignException('validation_failed', 'Identifier is invalid.', 422);
        return (int) $id;
    }

    private function now(): string { return date('Y-m-d H:i:s'); }
}
