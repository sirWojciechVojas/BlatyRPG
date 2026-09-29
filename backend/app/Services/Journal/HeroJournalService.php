<?php

namespace App\Services\Journal;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;
use App\Models\CharacterModel;
use App\Models\HeroJournalChecklistItemModel;
use App\Models\HeroJournalEntryModel;
use App\Models\HeroJournalNpcEncounterModel;
use App\Models\HeroJournalRelationModel;
use App\Models\HeroJournalSectionModel;
use App\Models\UserModel;
use App\Services\Campaign\CampaignAccessPolicy;
use CodeIgniter\Database\BaseConnection;

final class HeroJournalService
{
    private $db;
    private $entries;
    private $sections;
    private $checklist;
    private $relations;
    private $encounters;
    private $campaigns;
    private $members;
    private $characters;
    private $users;
    private $campaignPolicy;
    private $accessPolicy;
    private $validator;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->entries = new HeroJournalEntryModel($this->db);
        $this->sections = new HeroJournalSectionModel($this->db);
        $this->checklist = new HeroJournalChecklistItemModel($this->db);
        $this->relations = new HeroJournalRelationModel($this->db);
        $this->encounters = new HeroJournalNpcEncounterModel($this->db);
        $this->campaigns = new CampaignModel($this->db);
        $this->members = new CampaignMemberModel($this->db);
        $this->characters = new CharacterModel($this->db);
        $this->users = new UserModel($this->db);
        $this->campaignPolicy = new CampaignAccessPolicy();
        $this->accessPolicy = new HeroJournalAccessPolicy();
        $this->validator = new HeroJournalPayloadValidator();
    }

    public function list(int $campaignId, int $characterId, array $auth, array $filters = []): array
    {
        $context = $this->context($campaignId, $characterId, $auth);
        $query = $this->entries
            ->where('campaign_id', $campaignId)
            ->where('character_id', $characterId);

        $type = (string) ($filters['type'] ?? '');
        if ($type !== '') {
            if (!in_array($type, HeroJournalPayloadValidator::TYPES, true)) {
                throw new HeroJournalException('validation_failed', 'Entry type filter is invalid.', 422);
            }
            $query->where('entry_type', $type);
        }
        $status = (string) ($filters['status'] ?? '');
        if ($status !== '') {
            if (!in_array($status, HeroJournalPayloadValidator::STATUSES, true)) {
                throw new HeroJournalException('validation_failed', 'Entry status filter is invalid.', 422);
            }
            $query->where('status', $status);
        }
        if (!empty($filters['archived'])) {
            $query->where('archived_at IS NOT NULL', null, false);
        } else {
            $query->where('archived_at', null);
        }
        $rows = $query->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->findAll();
        $items = [];
        foreach ($rows as $row) {
            if ($this->canView($row, $context)) {
                $items[] = $this->presentSummary($row, $context);
            }
        }
        return [
            'items' => $items,
            'count' => count($items),
            'character' => $this->presentCharacter($context['character']),
            'capabilities' => [
                'canCreate' => $context['isCharacterOwner'] || $context['isManager'],
                'canManageCampaign' => $context['isManager'],
            ],
        ];
    }

    public function show(int $campaignId, int $entryId, array $auth): array
    {
        $entry = $this->entry($campaignId, $entryId);
        $context = $this->context($campaignId, (int) $entry['character_id'], $auth);
        $this->assertVisible($entry, $context);
        return [
            'entry' => $this->presentDetail($entry, $context),
            'character' => $this->presentCharacter($context['character']),
        ];
    }

    public function create(int $campaignId, array $auth, array $payload): array
    {
        $characterId = $this->positiveId(
            $payload['characterId'] ?? $payload['character_id'] ?? null,
            'characterId'
        );
        $context = $this->context($campaignId, $characterId, $auth);
        if (!$context['isCharacterOwner'] && !$context['isManager']) {
            throw new HeroJournalException(
                'forbidden',
                'Only a character owner or campaign manager can add journal entries.',
                403
            );
        }
        $validated = $this->validator->validateCreate($payload);
        $this->assertValid($validated);
        $now = date('Y-m-d H:i:s');
        $entryData = $validated['data'] + [
            'campaign_id' => $campaignId,
            'character_id' => $characterId,
            'owner_user_id' => $context['userId'],
            'author_user_id' => $context['userId'],
            'revision' => 1,
        ];

        $this->db->transBegin();
        try {
            if (!$this->entries->insert($entryData)) {
                throw new HeroJournalException(
                    'journal_write_failed',
                    'Journal entry could not be created.',
                    500,
                    $this->entries->errors()
                );
            }
            $entryId = (int) $this->entries->getInsertID();
            $this->syncNested($entryId, $campaignId, $context, $validated['nested'], $now);
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $this->show($campaignId, $entryId, $auth);
    }

    public function update(int $campaignId, int $entryId, array $auth, array $payload): array
    {
        $entry = $this->entry($campaignId, $entryId);
        $context = $this->context($campaignId, (int) $entry['character_id'], $auth);
        $this->assertEditable($entry, $context);
        $validated = $this->validator->validateUpdate($payload);
        $this->assertValid($validated);
        if ((int) $entry['owner_user_id'] !== $context['userId']
            && ($validated['data']['visibility'] ?? null) === 'private') {
            throw new HeroJournalException(
                'forbidden',
                'Only the entry owner can make an entry private.',
                403
            );
        }
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            $this->db->table('hero_journal_entries')
                ->set($validated['data'])
                ->set('updated_at', $now)
                ->set('revision', 'revision + 1', false)
                ->where('id', $entryId)
                ->where('campaign_id', $campaignId)
                ->where('revision', $validated['revision'])
                ->where('deleted_at', null)
                ->update();
            if ($this->db->affectedRows() !== 1) {
                $this->throwMissingOrConflict($campaignId, $entryId);
            }
            $this->syncNested(
                $entryId,
                $campaignId,
                $context,
                $validated['nested'],
                $now,
                $entry
            );
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $this->show($campaignId, $entryId, $auth);
    }

    public function setArchived(
        int $campaignId,
        int $entryId,
        array $auth,
        bool $archived,
        int $revision
    ): array {
        $entry = $this->entry($campaignId, $entryId);
        $context = $this->context($campaignId, (int) $entry['character_id'], $auth);
        $this->assertEditable($entry, $context);
        $now = date('Y-m-d H:i:s');
        $this->db->table('hero_journal_entries')
            ->set('archived_at', $archived ? $now : null)
            ->set('updated_at', $now)
            ->set('revision', 'revision + 1', false)
            ->where('id', $entryId)
            ->where('campaign_id', $campaignId)
            ->where('revision', $revision)
            ->where('deleted_at', null)
            ->update();
        if ($this->db->affectedRows() !== 1) {
            $this->throwMissingOrConflict($campaignId, $entryId);
        }
        return $this->show($campaignId, $entryId, $auth);
    }

    public function delete(int $campaignId, int $entryId, array $auth, int $revision): array
    {
        $entry = $this->entry($campaignId, $entryId);
        $context = $this->context($campaignId, (int) $entry['character_id'], $auth);
        $this->assertEditable($entry, $context);
        $now = date('Y-m-d H:i:s');
        $this->db->table('hero_journal_entries')
            ->set('deleted_at', $now)
            ->set('updated_at', $now)
            ->set('revision', 'revision + 1', false)
            ->where('id', $entryId)
            ->where('campaign_id', $campaignId)
            ->where('revision', $revision)
            ->where('deleted_at', null)
            ->update();
        if ($this->db->affectedRows() !== 1) {
            $this->throwMissingOrConflict($campaignId, $entryId);
        }
        return ['deleted' => true, 'id' => $entryId];
    }

    public function addChecklistItem(
        int $campaignId,
        int $entryId,
        array $auth,
        array $payload
    ): array {
        [$entry, $context] = $this->editableEntry($campaignId, $entryId, $auth);
        $validated = $this->validator->validateChecklist($payload, true);
        $this->assertValid($validated);
        $sortOrder = $this->checklist->where('entry_id', $entryId)->countAllResults();
        $data = $validated['data'] + [
            'entry_id' => $entryId,
            'sort_order' => $sortOrder,
            'is_completed' => 0,
        ];
        if (!$this->checklist->insert($data)) {
            throw new HeroJournalException('journal_write_failed', 'Checklist item could not be added.', 500);
        }
        $this->touch($entry, $context);
        return $this->show($campaignId, $entryId, $auth);
    }

    public function updateChecklistItem(
        int $campaignId,
        int $entryId,
        int $itemId,
        array $auth,
        array $payload
    ): array {
        [$entry, $context] = $this->editableEntry($campaignId, $entryId, $auth);
        $item = $this->checklist->where('entry_id', $entryId)->find($itemId);
        if (!$item) {
            throw new HeroJournalException('checklist_item_not_found', 'Checklist item was not found.', 404);
        }
        $validated = $this->validator->validateChecklist($payload, false);
        $this->assertValid($validated);
        $data = $validated['data'];
        if (array_key_exists('is_completed', $data)) {
            $data['completed_at'] = $data['is_completed'] ? date('Y-m-d H:i:s') : null;
        }
        if ($data && !$this->checklist->update($itemId, $data)) {
            throw new HeroJournalException('journal_write_failed', 'Checklist item could not be updated.', 500);
        }
        $this->touch($entry, $context);
        return $this->show($campaignId, $entryId, $auth);
    }

    public function deleteChecklistItem(
        int $campaignId,
        int $entryId,
        int $itemId,
        array $auth
    ): array {
        [$entry, $context] = $this->editableEntry($campaignId, $entryId, $auth);
        $item = $this->checklist->where('entry_id', $entryId)->find($itemId);
        if (!$item) {
            throw new HeroJournalException('checklist_item_not_found', 'Checklist item was not found.', 404);
        }
        $this->checklist->delete($itemId);
        $this->touch($entry, $context);
        return $this->show($campaignId, $entryId, $auth);
    }

    public function addRelation(
        int $campaignId,
        int $entryId,
        array $auth,
        array $payload
    ): array {
        [$entry, $context] = $this->editableEntry($campaignId, $entryId, $auth);
        $validated = $this->validator->validateRelation($payload);
        $this->assertValid($validated);
        $this->assertRelationTarget(
            $entryId,
            $campaignId,
            (int) $validated['data']['target_entry_id'],
            $context
        );
        $exists = $this->relations
            ->where('source_entry_id', $entryId)
            ->where('target_entry_id', $validated['data']['target_entry_id'])
            ->where('relation_type', $validated['data']['relation_type'])
            ->first();
        if (!$exists) {
            $this->relations->insert($validated['data'] + [
                'source_entry_id' => $entryId,
                'created_by_user_id' => $context['userId'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->touch($entry, $context);
        return $this->show($campaignId, $entryId, $auth);
    }

    public function deleteRelation(
        int $campaignId,
        int $entryId,
        int $relationId,
        array $auth
    ): array {
        [$entry, $context] = $this->editableEntry($campaignId, $entryId, $auth);
        $relation = $this->relations->where('source_entry_id', $entryId)->find($relationId);
        if (!$relation) {
            throw new HeroJournalException('relation_not_found', 'Journal relation was not found.', 404);
        }
        $this->relations->delete($relationId);
        $this->touch($entry, $context);
        return $this->show($campaignId, $entryId, $auth);
    }

    private function context(int $campaignId, int $characterId, array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new HeroJournalException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->users->where('id', $userId)->where('deleted_at', null)->first();
        if (!$user) {
            throw new HeroJournalException('unauthorized', 'Authentication is required.', 401);
        }
        $campaign = $this->campaigns->find($campaignId);
        if (!$campaign) {
            throw new HeroJournalException('campaign_not_found', 'Campaign was not found.', 404);
        }
        $membership = $this->members
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->first();
        $verifiedAuth = $auth + ['user_id' => $userId];
        $verifiedAuth['role'] = strtolower((string) ($user['role'] ?? 'user'));
        $access = $this->campaignPolicy->evaluate($verifiedAuth, $campaign, $membership);
        if (!$access['canAccess']) {
            throw new HeroJournalException('forbidden', 'Campaign is outside your access scope.', 403);
        }
        $character = $this->characters->find($characterId);
        if (!$character || !$this->characterBelongsToCampaign($character, $campaignId)) {
            throw new HeroJournalException('character_not_found', 'Character was not found in this campaign.', 404);
        }
        $explicitOwner = $this->db->table('resource_permissions')
            ->where('campaign_id', $campaignId)
            ->where('resource_type', 'character')
            ->where('resource_id', $characterId)
            ->where('user_id', $userId)
            ->where('access_level', 'owner')
            ->countAllResults() > 0;
        return [
            'userId' => $userId,
            'isManager' => !empty($access['canManage']),
            'membershipRole' => strtolower((string) ($membership['role'] ?? '')),
            'isCharacterOwner' => (int) ($character['user_id'] ?? 0) === $userId || $explicitOwner,
            'character' => $character,
        ];
    }

    private function characterBelongsToCampaign(array $character, int $campaignId): bool
    {
        if ((int) ($character['campaign_id'] ?? 0) === $campaignId) {
            return true;
        }
        return $this->db->table('character_campaigns')
            ->where('character_id', (int) $character['id'])
            ->where('campaign_id', $campaignId)
            ->countAllResults() > 0;
    }

    private function canView(array $entry, array $context, ?string $override = null): bool
    {
        return $this->accessPolicy->canView($entry, $context, $override);
    }

    private function canEdit(array $entry, array $context): bool
    {
        return $this->accessPolicy->canEdit($entry, $context);
    }

    private function presentSummary(array $entry, array $context): array
    {
        return [
            'id' => (int) $entry['id'],
            'characterId' => (int) $entry['character_id'],
            'type' => (string) $entry['entry_type'],
            'title' => (string) $entry['title'],
            'status' => (string) $entry['status'],
            'summary' => (string) ($entry['summary'] ?? ''),
            'sessionNumber' => $entry['session_number'] === null ? null : (int) $entry['session_number'],
            'occurredOn' => $entry['occurred_on'],
            'visibility' => (string) $entry['visibility'],
            'archived' => $entry['archived_at'] !== null,
            'updatedAt' => $entry['updated_at'],
            'revision' => (int) $entry['revision'],
            'capabilities' => [
                'canEdit' => $this->canEdit($entry, $context),
                'canEditPrivate' => (int) $entry['owner_user_id'] === $context['userId'],
            ],
        ];
    }

    private function presentDetail(array $entry, array $context): array
    {
        $result = $this->presentSummary($entry, $context);
        $author = $this->users->find((int) $entry['author_user_id']);
        $sections = [];
        foreach ($this->sections->where('entry_id', $entry['id'])->orderBy('sort_order', 'ASC')->findAll() as $section) {
            $visibility = $section['visibility'] ?: $entry['visibility'];
            if (!$this->canView($entry, $context, $visibility)) {
                continue;
            }
            $sections[] = [
                'id' => (int) $section['id'],
                'key' => (string) $section['section_key'],
                'content' => (string) ($section['content'] ?? ''),
                'visibility' => $section['visibility'],
            ];
        }
        $checklist = array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'label' => (string) $item['label'],
                'isCompleted' => !empty($item['is_completed']),
                'completedAt' => $item['completed_at'],
            ];
        }, $this->checklist->where('entry_id', $entry['id'])->orderBy('sort_order', 'ASC')->findAll());
        $encounters = array_map(static function (array $item): array {
            return [
                'id' => (int) $item['id'],
                'sessionNumber' => $item['session_number'] === null ? null : (int) $item['session_number'],
                'occurredOn' => $item['occurred_on'],
                'summary' => (string) $item['summary'],
            ];
        }, $this->encounters->where('entry_id', $entry['id'])->orderBy('sort_order', 'ASC')->findAll());

        return $result + [
            'ownerUserId' => (int) $entry['owner_user_id'],
            'author' => [
                'id' => (int) $entry['author_user_id'],
                'name' => (string) ($author['username'] ?? ''),
            ],
            'trustLevel' => $entry['trust_level'] === null ? null : (int) $entry['trust_level'],
            'createdAt' => $entry['created_at'],
            'sections' => $sections,
            'checklist' => $checklist,
            'encounters' => $encounters,
            'relations' => $this->presentRelations($entry, $context),
        ];
    }

    private function presentRelations(array $entry, array $context): array
    {
        $result = [];
        $rows = $this->relations->where('source_entry_id', $entry['id'])->orderBy('id', 'ASC')->findAll();
        foreach ($rows as $row) {
            $target = $this->entries->find((int) $row['target_entry_id']);
            if (!$target || (int) $target['campaign_id'] !== (int) $entry['campaign_id']
                || !$this->canView($target, $context)) {
                continue;
            }
            $result[] = [
                'id' => (int) $row['id'],
                'relationType' => (string) $row['relation_type'],
                'target' => $this->presentSummary($target, $context),
            ];
        }
        return $result;
    }

    private function syncNested(
        int $entryId,
        int $campaignId,
        array $context,
        array $nested,
        string $now,
        ?array $existingEntry = null
    ): void {
        if (array_key_exists('sections', $nested)) {
            $preservePrivate = $existingEntry
                && (int) $existingEntry['owner_user_id'] !== (int) $context['userId'];
            $delete = $this->db->table('hero_journal_sections')->where('entry_id', $entryId);
            if ($preservePrivate) {
                $delete->groupStart()->where('visibility !=', 'private')->orWhere('visibility', null)->groupEnd();
            }
            $delete->delete();
            $sectionRows = $preservePrivate
                ? array_values(array_filter($nested['sections'], static function (array $row): bool {
                    return ($row['visibility'] ?? null) !== 'private';
                }))
                : $nested['sections'];
            $rows = array_map(static function (array $row) use ($entryId, $now): array {
                return $row + ['entry_id' => $entryId, 'created_at' => $now, 'updated_at' => $now];
            }, $sectionRows);
            if ($rows) {
                $this->db->table('hero_journal_sections')->insertBatch($rows);
            }
        }
        if (array_key_exists('checklist', $nested)) {
            $this->db->table('hero_journal_checklist_items')->where('entry_id', $entryId)->delete();
            $rows = array_map(static function (array $row) use ($entryId, $now): array {
                unset($row['id']);
                return $row + [
                    'entry_id' => $entryId,
                    'completed_at' => $row['is_completed'] ? $now : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $nested['checklist']);
            if ($rows) {
                $this->db->table('hero_journal_checklist_items')->insertBatch($rows);
            }
        }
        if (array_key_exists('encounters', $nested)) {
            $this->db->table('hero_journal_npc_encounters')->where('entry_id', $entryId)->delete();
            $rows = array_map(static function (array $row) use ($entryId, $now): array {
                unset($row['id']);
                return $row + ['entry_id' => $entryId, 'created_at' => $now, 'updated_at' => $now];
            }, $nested['encounters']);
            if ($rows) {
                $this->db->table('hero_journal_npc_encounters')->insertBatch($rows);
            }
        }
        if (array_key_exists('relations', $nested)) {
            foreach ($nested['relations'] as $relation) {
                $this->assertRelationTarget(
                    $entryId,
                    $campaignId,
                    (int) $relation['target_entry_id'],
                    $context
                );
            }
            $this->db->table('hero_journal_relations')->where('source_entry_id', $entryId)->delete();
            $rows = array_map(static function (array $row) use ($entryId, $context, $now): array {
                return $row + [
                    'source_entry_id' => $entryId,
                    'created_by_user_id' => $context['userId'],
                    'created_at' => $now,
                ];
            }, $nested['relations']);
            if ($rows) {
                $this->db->table('hero_journal_relations')->insertBatch($rows);
            }
        }
    }

    private function editableEntry(int $campaignId, int $entryId, array $auth): array
    {
        $entry = $this->entry($campaignId, $entryId);
        $context = $this->context($campaignId, (int) $entry['character_id'], $auth);
        $this->assertEditable($entry, $context);
        return [$entry, $context];
    }

    private function touch(array $entry, array $context): void
    {
        $this->assertEditable($entry, $context);
        $this->db->table('hero_journal_entries')
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)
            ->where('id', (int) $entry['id'])
            ->where('revision', (int) $entry['revision'])
            ->where('deleted_at', null)
            ->update();
        if ($this->db->affectedRows() !== 1) {
            $this->throwMissingOrConflict((int) $entry['campaign_id'], (int) $entry['id']);
        }
    }

    private function assertRelationTarget(
        int $entryId,
        int $campaignId,
        int $targetId,
        array $context
    ): void {
        if ($targetId === $entryId) {
            throw new HeroJournalException('validation_failed', 'An entry cannot relate to itself.', 422);
        }
        $target = $this->entries->find($targetId);
        if (!$target || (int) $target['campaign_id'] !== $campaignId || !$this->canView($target, $context)) {
            throw new HeroJournalException('relation_target_not_found', 'Related entry was not found.', 404);
        }
    }

    private function assertVisible(array $entry, array $context): void
    {
        if (!$this->canView($entry, $context)) {
            throw new HeroJournalException('journal_entry_not_found', 'Journal entry was not found.', 404);
        }
    }

    private function assertEditable(array $entry, array $context): void
    {
        $this->assertVisible($entry, $context);
        if (!$this->canEdit($entry, $context)) {
            throw new HeroJournalException('forbidden', 'You cannot edit this journal entry.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new HeroJournalException(
                'validation_failed',
                'Journal payload is invalid.',
                422,
                $validated['errors'] ?? []
            );
        }
    }

    private function entry(int $campaignId, int $entryId): array
    {
        $entry = $entryId > 0 ? $this->entries->find($entryId) : null;
        if (!$entry || (int) $entry['campaign_id'] !== $campaignId) {
            throw new HeroJournalException('journal_entry_not_found', 'Journal entry was not found.', 404);
        }
        return $entry;
    }

    private function throwMissingOrConflict(int $campaignId, int $entryId): void
    {
        $entry = $this->entries->find($entryId);
        if (!$entry || (int) $entry['campaign_id'] !== $campaignId) {
            throw new HeroJournalException('journal_entry_not_found', 'Journal entry was not found.', 404);
        }
        throw new HeroJournalException(
            'journal_conflict',
            'Journal entry changed on the server. Reload it and try again.',
            409,
            ['revision' => (int) $entry['revision']]
        );
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new HeroJournalException('journal_write_failed', 'Journal changes could not be saved.', 500);
        }
        $this->db->transCommit();
    }

    private function positiveId($value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new HeroJournalException(
                'validation_failed',
                'A valid identifier is required.',
                422,
                [$field => 'A positive integer is required.']
            );
        }
        return (int) $id;
    }

    private function presentCharacter(array $character): array
    {
        return ['id' => (int) $character['id'], 'name' => (string) $character['name']];
    }
}
