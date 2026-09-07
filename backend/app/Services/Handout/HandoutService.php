<?php

namespace App\Services\Handout;

use App\Models\HandoutAssetModel;
use App\Models\HandoutLibraryEntryModel;
use App\Models\HandoutLibraryFolderModel;
use App\Models\HandoutLibraryTagModel;
use App\Models\JournalModel;
use App\Services\Authorization\AccessLevel;
use App\Services\Authorization\ResourcePermissionService;
use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Authoritative CRUD, ACL and snapshot publishing for handouts. */
final class HandoutService
{
    private $db;
    private $access;
    private $content;
    private $storage;
    private $entries;
    private $folders;
    private $tags;
    private $assets;
    private $journals;
    private $permissions;

    public function __construct(
        ?BaseConnection $db = null,
        ?HandoutAccessService $access = null,
        ?HandoutContentValidator $content = null,
        ?HandoutAssetStorage $storage = null,
        ?ResourcePermissionService $permissions = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->access = $access ?: new HandoutAccessService($this->db);
        $this->content = $content ?: new HandoutContentValidator();
        $this->storage = $storage ?: new HandoutAssetStorage();
        $this->entries = new HandoutLibraryEntryModel($this->db);
        $this->folders = new HandoutLibraryFolderModel($this->db);
        $this->tags = new HandoutLibraryTagModel($this->db);
        $this->assets = new HandoutAssetModel($this->db);
        $this->journals = new JournalModel($this->db);
        $this->permissions = $permissions ?: new ResourcePermissionService($this->db);
    }

    public function listLibrary(array $auth, array $query): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $trash = $this->truthy($query['trash'] ?? false);
        $limit = $this->limit($query['limit'] ?? 30);
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $builder = $this->db->table('handout_library_entries')
            ->where('owner_user_id', $ownerId);
        $trash ? $builder->where('deleted_at IS NOT NULL', null, false) : $builder->where('deleted_at', null);
        $folderId = $this->idOrNull($query['folderId'] ?? $query['folder_id'] ?? null);
        if ($folderId !== null) $builder->where('folder_id', $folderId);
        $term = mb_substr(trim((string) ($query['q'] ?? '')), 0, 120);
        if ($term !== '') {
            $builder->groupStart()->like('title', $term)->orLike('search_text', $term)->groupEnd();
        }
        $tagId = $this->idOrNull($query['tagId'] ?? $query['tag_id'] ?? null);
        if ($tagId !== null) {
            $builder->join('handout_library_entry_tags et', 'et.entry_id = handout_library_entries.id', 'inner')
                ->where('et.tag_id', $tagId);
        }
        $rows = $builder->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')
            ->get($limit + 1, $offset)->getResultArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) $rows = array_slice($rows, 0, $limit);
        return [
            'folders' => $this->libraryFolders($ownerId, $trash),
            'tags' => $this->libraryTags($ownerId),
            'items' => array_map(function (array $entry): array {
                return $this->presentLibraryEntry($entry, false);
            }, $rows),
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'hasMore' => $hasMore],
        ];
    }

    public function getLibraryEntry(array $auth, int $entryId): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        return ['entry' => $this->presentLibraryEntry($this->libraryEntry($ownerId, $entryId, true), true)];
    }

    public function createFolder(array $auth, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $name = $this->name($payload['name'] ?? null, 150, 'name');
        $parentId = $this->idOrNull($payload['parentId'] ?? $payload['parent_id'] ?? null);
        if ($parentId !== null) $this->folder($ownerId, $parentId, false);
        $now = $this->now();
        $id = $this->folders->insert([
            'owner_user_id' => $ownerId, 'parent_id' => $parentId, 'name' => $name,
            'sort_order' => (int) ($payload['sortOrder'] ?? $payload['sort_order'] ?? 0),
            'created_at' => $now, 'updated_at' => $now,
        ], true);
        if (!$id) throw new CampaignException('handout_write_failed', 'Folder could not be saved.', 500);
        return ['folder' => $this->folder($ownerId, (int) $id, false)];
    }

    public function updateFolder(array $auth, int $folderId, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $folder = $this->folder($ownerId, $folderId, false);
        $data = [];
        if (array_key_exists('name', $payload)) $data['name'] = $this->name($payload['name'], 150, 'name');
        if (array_key_exists('sortOrder', $payload) || array_key_exists('sort_order', $payload)) {
            $data['sort_order'] = (int) ($payload['sortOrder'] ?? $payload['sort_order']);
        }
        if (array_key_exists('parentId', $payload) || array_key_exists('parent_id', $payload)) {
            $parentId = $this->idOrNull($payload['parentId'] ?? $payload['parent_id']);
            if ($parentId !== null) {
                if ($parentId === $folderId || $this->folderDescendsFrom($ownerId, $parentId, $folderId)) {
                    throw new CampaignException('validation_failed', 'Folder cannot become its own descendant.', 422, ['parentId' => 'Folder hierarchy is invalid.']);
                }
                $this->folder($ownerId, $parentId, false);
            }
            $data['parent_id'] = $parentId;
        }
        if (!$data) return ['folder' => $folder];
        $data['updated_at'] = $this->now();
        $this->folders->update($folderId, $data);
        return ['folder' => $this->folder($ownerId, $folderId, false)];
    }

    public function trashFolder(array $auth, int $folderId): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $this->folder($ownerId, $folderId, false);
        $children = $this->db->table('handout_library_folders')->where('owner_user_id', $ownerId)
            ->where('parent_id', $folderId)->where('deleted_at', null)->countAllResults();
        $entries = $this->db->table('handout_library_entries')->where('owner_user_id', $ownerId)
            ->where('folder_id', $folderId)->where('deleted_at', null)->countAllResults();
        if ($children || $entries) {
            throw new CampaignException('folder_not_empty', 'Move entries and subfolders before deleting this folder.', 409);
        }
        $this->folders->update($folderId, ['deleted_at' => $this->now(), 'updated_at' => $this->now()]);
        return ['deleted' => true, 'id' => $folderId];
    }

    public function createTag(array $auth, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $name = $this->name($payload['name'] ?? null, 80, 'name');
        $existing = $this->tags->where('owner_user_id', $ownerId)->where('name', $name)->first();
        if ($existing) return ['tag' => $this->presentTag($existing)];
        $now = $this->now();
        $id = $this->tags->insert(['owner_user_id' => $ownerId, 'name' => $name, 'created_at' => $now, 'updated_at' => $now], true);
        if (!$id) throw new CampaignException('handout_write_failed', 'Tag could not be saved.', 500);
        return ['tag' => $this->presentTag($this->tags->find((int) $id))];
    }

    public function deleteTag(array $auth, int $tagId): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $tag = $this->tags->where('id', $tagId)->where('owner_user_id', $ownerId)->first();
        if (!$tag) throw new CampaignException('handout_tag_not_found', 'Tag was not found.', 404);
        $this->tags->delete($tagId);
        return ['deleted' => true, 'id' => $tagId];
    }

    public function createLibraryEntry(array $auth, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $validated = $this->libraryEntryPayload($ownerId, $payload, false);
        $now = $this->now();
        $this->db->transBegin();
        try {
            $id = $this->entries->insert(array_merge($validated['data'], [
                'owner_user_id' => $ownerId, 'revision' => 1, 'created_at' => $now, 'updated_at' => $now,
            ]), true);
            if (!$id) throw new CampaignException('handout_write_failed', 'Library entry could not be saved.', 500);
            $this->syncEntryTags((int) $id, $validated['tagIds']);
            $this->syncAssetReferences($ownerId, $validated['assetIds'], 'library', (int) $id);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['entry' => $this->presentLibraryEntry($this->libraryEntry($ownerId, (int) $id, true), true)];
    }

    public function updateLibraryEntry(array $auth, int $entryId, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $entry = $this->libraryEntry($ownerId, $entryId, false);
        $revision = $this->revision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $validated = $this->libraryEntryPayload($ownerId, $payload, true, $entry);
        $this->db->transBegin();
        try {
            $data = array_merge($validated['data'], ['updated_at' => $this->now()]);
            $this->db->table('handout_library_entries')->set($data)->set('revision', 'revision + 1', false)
                ->where('id', $entryId)->where('owner_user_id', $ownerId)->where('revision', $revision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictForEntry($ownerId, $entryId);
            $this->syncEntryTags($entryId, $validated['tagIds']);
            $this->syncAssetReferences($ownerId, $validated['assetIds'], 'library', $entryId);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['entry' => $this->presentLibraryEntry($this->libraryEntry($ownerId, $entryId, true), true)];
    }

    public function trashLibraryEntry(array $auth, int $entryId, array $payload): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $entry = $this->libraryEntry($ownerId, $entryId, false);
        $revision = $this->revision($payload);
        if ((int) $entry['revision'] !== $revision) $this->conflict((int) $entry['revision']);
        $this->db->transBegin();
        try {
            $this->db->table('handout_library_entries')->set(['deleted_at' => $this->now(), 'updated_at' => $this->now()])
                ->set('revision', 'revision + 1', false)->where('id', $entryId)->where('owner_user_id', $ownerId)
                ->where('revision', $revision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictForEntry($ownerId, $entryId);
            $this->removeAssetReferences('library', $entryId);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['deleted' => true, 'id' => $entryId];
    }

    public function restoreLibraryEntry(array $auth, int $entryId): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $entry = $this->libraryEntry($ownerId, $entryId, true);
        if (empty($entry['deleted_at'])) return ['entry' => $this->presentLibraryEntry($entry, true)];
        $content = $this->content->validate($entry['content_json'], false);
        if (!$content['valid']) throw new CampaignException('handout_restore_failed', 'Handout content cannot be restored.', 409);
        $this->db->transBegin();
        try {
            $this->entries->update($entryId, ['deleted_at' => null, 'updated_at' => $this->now()]);
            $this->syncAssetReferences($ownerId, $content['assetIds'], 'library', $entryId);
            $this->commit('handout_restore_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['entry' => $this->presentLibraryEntry($this->libraryEntry($ownerId, $entryId, true), true)];
    }

    public function uploadAsset(array $auth, ?UploadedFile $file): array
    {
        $ownerId = $this->access->libraryOwner($auth);
        $size = $file ? (int) $file->getSize() : 0;
        $used = (int) ($this->db->table('handout_assets')->selectSum('byte_size', 'used')
            ->where('owner_user_id', $ownerId)->where('deleted_at', null)->get()->getRowArray()['used'] ?? 0);
        if ($size < 1 || $used + $size > $this->storage->quotaBytes()) {
            throw new CampaignException('handout_quota_exceeded', 'Your handout library storage limit has been reached.', 422);
        }
        $stored = $this->storage->store($ownerId, $file);
        try {
            $now = $this->now();
            $id = $this->assets->insert([
                'owner_user_id' => $ownerId, 'storage_key' => $stored['storageKey'],
                'original_name' => $stored['originalName'], 'mime_type' => $stored['mimeType'],
                'byte_size' => (int) $stored['byteSize'], 'sha256' => $stored['sha256'],
                'created_at' => $now, 'updated_at' => $now,
            ], true);
            if (!$id) throw new CampaignException('handout_storage_failed', 'Asset metadata could not be saved.', 500);
            return ['asset' => $this->presentAsset($this->assets->find((int) $id))];
        } catch (\Throwable $exception) {
            $this->storage->remove($stored['storageKey']);
            throw $exception;
        }
    }

    public function assetForDownload(array $auth, int $assetId): array
    {
        $asset = $this->assets->where('id', $assetId)->where('deleted_at', null)->first();
        if (!$asset) throw new CampaignException('handout_asset_not_found', 'Handout asset was not found.', 404);
        $ownerId = $this->access->libraryOwner($auth);
        if ((int) $asset['owner_user_id'] !== $ownerId && !$this->canViewReferencedAsset($auth, $assetId)) {
            throw new CampaignException('handout_asset_not_found', 'Handout asset was not found.', 404);
        }
        $path = $this->storage->path((string) $asset['storage_key']);
        if (!is_file($path)) throw new CampaignException('handout_asset_not_found', 'Handout asset was not found.', 404);
        return ['asset' => $asset, 'path' => $path];
    }

    public function listCampaign(int $campaignId, array $auth, array $query): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $trash = $this->truthy($query['trash'] ?? false);
        if ($trash && empty($context['capabilities']['canManage'])) {
            throw new CampaignException('forbidden', 'Only campaign leaders may view the handout trash.', 403);
        }
        $limit = $this->limit($query['limit'] ?? 30);
        $offset = max(0, (int) ($query['offset'] ?? 0));
        $builder = $this->db->table('journals')->where('campaign_id', $campaignId)->where('kind', 'handout');
        $trash ? $builder->where('deleted_at IS NOT NULL', null, false) : $builder->where('deleted_at', null);
        if (empty($context['capabilities']['canManage'])) {
            $userId = (int) $context['auth']['user_id'];
            $builder->join(
                'journal_recipients jr',
                'jr.journal_id = journals.id AND jr.user_id = ' . $this->db->escape($userId),
                'left'
            )->groupStart()->where('audience_mode', 'all_active_members')
                ->orGroupStart()->where('audience_mode', 'selected_active_members')
                ->where('jr.user_id IS NOT NULL', null, false)->groupEnd()->groupEnd();
        }
        $term = mb_substr(trim((string) ($query['q'] ?? '')), 0, 120);
        if ($term !== '') $builder->groupStart()->like('title', $term)->orLike('search_text', $term)->groupEnd();
        $tag = mb_substr(trim((string) ($query['tag'] ?? '')), 0, 80);
        if ($tag !== '') $builder->like('tag_snapshot_json', json_encode($tag, JSON_UNESCAPED_UNICODE));
        $rows = $builder->orderBy('updated_at', 'DESC')->orderBy('id', 'DESC')->get($limit + 1, $offset)->getResultArray();
        $hasMore = count($rows) > $limit;
        if ($hasMore) $rows = array_slice($rows, 0, $limit);
        return [
            'items' => array_map(function (array $journal) use ($context): array {
                return $this->presentJournal($context, $journal, false);
            }, $rows),
            'pagination' => ['offset' => $offset, 'limit' => $limit, 'hasMore' => $hasMore],
            'capabilities' => ['canManageLibrary' => !empty($context['capabilities']['canManage'])],
        ];
    }

    public function getCampaignHandout(int $campaignId, int $journalId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $journal = $this->journal($campaignId, $journalId, true);
        $this->access->requireView($context, $journal);
        return ['handout' => $this->presentJournal($context, $journal, true)];
    }

    public function publish(int $campaignId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $this->access->requirePresent($context);
        $ownerId = $this->access->libraryOwner($auth);
        $sourceId = $this->requiredId($payload['libraryEntryId'] ?? $payload['library_entry_id'] ?? null, 'libraryEntryId');
        $source = $this->libraryEntry($ownerId, $sourceId, false);
        $content = $this->content->validate($source['content_json'], false);
        if (!$content['valid']) throw new CampaignException('validation_failed', 'Library content is invalid.', 422, $content['errors']);
        $now = $this->now();
        $this->db->transBegin();
        try {
            $id = $this->journals->insert([
                'campaign_id' => $campaignId, 'source_library_entry_id' => $sourceId,
                'author_user_id' => $ownerId, 'kind' => 'handout', 'title' => $source['title'],
                'content_json' => $content['json'], 'search_text' => $content['searchText'],
                'tag_snapshot_json' => $this->tagsForEntry($sourceId),
                'folder_path_snapshot' => $this->folderPath($ownerId, $source['folder_id'] ?? null),
                'audience_mode' => 'private', 'revision' => 1, 'published_at' => $now,
                'created_at' => $now, 'updated_at' => $now,
            ], true);
            if (!$id) throw new CampaignException('handout_write_failed', 'Handout could not be published.', 500);
            $this->syncAssetReferences($ownerId, $content['assetIds'], 'campaign', (int) $id);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['handout' => $this->presentJournal($context, $this->journal($campaignId, (int) $id, true), true)];
    }

    public function updateCampaignHandout(int $campaignId, int $journalId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $journal = $this->journal($campaignId, $journalId, false);
        $this->access->requireEdit($context, $journal);
        $revision = $this->revision($payload);
        if ((int) $journal['revision'] !== $revision) $this->conflict((int) $journal['revision']);
        $title = array_key_exists('title', $payload) ? $this->name($payload['title'], 180, 'title') : $journal['title'];
        $rawContent = array_key_exists('content', $payload) ? $payload['content'] : $journal['content_json'];
        $content = $this->content->validate($rawContent, true);
        if (!$content['valid']) throw new CampaignException('validation_failed', 'Handout content is invalid.', 422, $content['errors']);
        $related = array_key_exists('relatedLinks', $payload) || array_key_exists('related_links', $payload)
            ? $this->relatedLinks($payload['relatedLinks'] ?? $payload['related_links'])
            : $this->relatedLinksForJournal($journalId);
        $links = array_merge($content['mentions'], $related);
        foreach ($links as $link) $this->validateCampaignTarget($auth, $campaignId, $link['targetType'], $link['targetId']);
        $tagSnapshot = array_key_exists('tags', $payload) ? $this->tagNames($payload['tags']) : (array) ($journal['tag_snapshot_json'] ?? []);
        $this->db->transBegin();
        try {
            $this->db->table('journals')->set([
                'title' => $title, 'content_json' => $content['json'],
                'search_text' => $content['searchText'], 'tag_snapshot_json' => json_encode($tagSnapshot, JSON_UNESCAPED_UNICODE),
                'updated_at' => $this->now(),
            ])->set('revision', 'revision + 1', false)->where('id', $journalId)->where('campaign_id', $campaignId)
                ->where('revision', $revision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictForJournal($campaignId, $journalId);
            $this->syncJournalLinks($journalId, $content['mentions'], $related);
            $this->syncAssetReferences((int) $journal['author_user_id'], $content['assetIds'], 'campaign', $journalId, true);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['handout' => $this->presentJournal($context, $this->journal($campaignId, $journalId, true), true)];
    }

    public function share(int $campaignId, int $journalId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $journal = $this->journal($campaignId, $journalId, false);
        $this->access->requirePresent($context);
        $revision = $this->revision($payload);
        if ((int) $journal['revision'] !== $revision) $this->conflict((int) $journal['revision']);
        $mode = strtolower(trim((string) ($payload['audienceMode'] ?? $payload['audience_mode'] ?? 'private')));
        if (!in_array($mode, ['private', 'all_active_members', 'selected_active_members'], true)) {
            throw new CampaignException('validation_failed', 'Audience mode is invalid.', 422, ['audienceMode' => 'Audience mode is invalid.']);
        }
        $recipientIds = $mode === 'selected_active_members'
            ? $this->activeRecipients($campaignId, $payload['recipientIds'] ?? $payload['recipient_ids'] ?? [], true)
            : [];
        $notifyIds = $mode === 'private' ? [] : $this->activeRecipients($campaignId, $recipientIds, false, $mode === 'all_active_members');
        $notifyIds = array_values(array_filter($notifyIds, function (int $recipientId) use ($context): bool {
            return $recipientId !== (int) $context['auth']['user_id'];
        }));
        $batchId = $this->uuid();
        $this->db->transBegin();
        try {
            $this->db->table('journals')->set(['audience_mode' => $mode, 'updated_at' => $this->now()])
                ->set('revision', 'revision + 1', false)->where('id', $journalId)->where('campaign_id', $campaignId)
                ->where('revision', $revision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictForJournal($campaignId, $journalId);
            $this->db->table('journal_recipients')->where('journal_id', $journalId)->delete();
            foreach ($recipientIds as $recipientId) {
                $this->db->table('journal_recipients')->insert(['journal_id' => $journalId, 'user_id' => $recipientId, 'created_at' => $this->now()]);
            }
            $this->upsertNotifications($journalId, $notifyIds, $batchId, (int) $context['auth']['user_id']);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $updated = $this->journal($campaignId, $journalId, true);
        return [
            'handout' => $this->presentJournal($context, $updated, true),
            'delivery' => ['batchId' => $batchId, 'recipientUserIds' => $notifyIds, 'handoutId' => $journalId],
        ];
    }

    public function transferAuthor(int $campaignId, int $journalId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        if (empty($context['isOwner'])) throw new CampaignException('forbidden', 'Only the campaign owner may transfer authorship.', 403);
        $journal = $this->journal($campaignId, $journalId, false);
        $revision = $this->revision($payload);
        if ((int) $journal['revision'] !== $revision) $this->conflict((int) $journal['revision']);
        $targetUserId = $this->requiredId($payload['userId'] ?? $payload['user_id'] ?? null, 'userId');
        if (!$this->isActiveLeader($campaignId, $targetUserId)) {
            throw new CampaignException('validation_failed', 'Choose an active campaign leader.', 422, ['userId' => 'Target must be an active game master or assistant.']);
        }
        $this->db->table('journals')->set(['author_user_id' => $targetUserId, 'updated_at' => $this->now()])
            ->set('revision', 'revision + 1', false)->where('id', $journalId)->where('campaign_id', $campaignId)
            ->where('revision', $revision)->where('deleted_at', null)->update();
        if ($this->db->affectedRows() !== 1) $this->conflictForJournal($campaignId, $journalId);
        return ['handout' => $this->presentJournal($context, $this->journal($campaignId, $journalId, true), true)];
    }

    public function trashCampaignHandout(int $campaignId, int $journalId, array $auth, array $payload): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $journal = $this->journal($campaignId, $journalId, false);
        $this->access->requireEdit($context, $journal);
        $revision = $this->revision($payload);
        if ((int) $journal['revision'] !== $revision) $this->conflict((int) $journal['revision']);
        $this->db->transBegin();
        try {
            $this->db->table('journals')->set(['deleted_at' => $this->now(), 'updated_at' => $this->now()])
                ->set('revision', 'revision + 1', false)->where('id', $journalId)->where('campaign_id', $campaignId)
                ->where('revision', $revision)->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) $this->conflictForJournal($campaignId, $journalId);
            $this->removeAssetReferences('campaign', $journalId);
            $this->commit('handout_write_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['deleted' => true, 'id' => $journalId];
    }

    public function restoreCampaignHandout(int $campaignId, int $journalId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $journal = $this->journal($campaignId, $journalId, true);
        $this->access->requireEdit($context, $journal);
        if (empty($journal['deleted_at'])) return ['handout' => $this->presentJournal($context, $journal, true)];
        $content = $this->content->validate($journal['content_json'], true);
        if (!$content['valid']) throw new CampaignException('handout_restore_failed', 'Handout content cannot be restored.', 409);
        $this->db->transBegin();
        try {
            $this->journals->update($journalId, ['deleted_at' => null, 'updated_at' => $this->now()]);
            $this->syncAssetReferences((int) $journal['author_user_id'], $content['assetIds'], 'campaign', $journalId, true);
            $this->commit('handout_restore_failed');
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['handout' => $this->presentJournal($context, $this->journal($campaignId, $journalId, true), true)];
    }

    public function listNotifications(int $campaignId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $userId = (int) $context['auth']['user_id'];
        $rows = $this->db->table('journal_notifications n')->select('n.*, j.title, j.campaign_id, j.deleted_at')
            ->join('journals j', 'j.id = n.journal_id', 'inner')->where('n.user_id', $userId)
            ->where('j.campaign_id', $campaignId)->where('j.kind', 'handout')->orderBy('n.updated_at', 'DESC')->get()->getResultArray();
        $items = [];
        foreach ($rows as $row) {
            if (!empty($row['deleted_at'])) continue;
            $journal = $this->journal($campaignId, (int) $row['journal_id'], true);
            if (!$this->access->canView($context, $journal)) continue;
            $items[] = [
                'id' => (int) $row['id'], 'handoutId' => (int) $row['journal_id'], 'title' => (string) $row['title'],
                'batchId' => (string) $row['batch_id'], 'readAt' => $row['read_at'], 'createdAt' => $row['created_at'],
            ];
        }
        return ['items' => $items, 'unreadCount' => count(array_filter($items, static function (array $item): bool { return empty($item['readAt']); }))];
    }

    public function markNotificationRead(int $campaignId, int $notificationId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $row = $this->db->table('journal_notifications n')->select('n.*, j.campaign_id, j.deleted_at')
            ->join('journals j', 'j.id = n.journal_id', 'inner')->where('n.id', $notificationId)
            ->where('n.user_id', (int) $context['auth']['user_id'])->where('j.campaign_id', $campaignId)->get()->getRowArray();
        if (!$row || !empty($row['deleted_at'])) throw new CampaignException('handout_notification_not_found', 'Notification was not found.', 404);
        $this->access->requireView($context, $this->journal($campaignId, (int) $row['journal_id'], true));
        $now = $this->now();
        $this->db->table('journal_notifications')->where('id', $notificationId)->update(['read_at' => $now, 'updated_at' => $now]);
        return ['id' => $notificationId, 'readAt' => $now];
    }

    public function deliveryTargets(int $campaignId, string $batchId, array $auth): array
    {
        $context = $this->access->campaign($auth, $campaignId);
        $this->access->requirePresent($context);
        $rows = $this->db->table('journal_notifications n')->select('n.id, n.user_id, n.journal_id, j.title')
            ->join('journals j', 'j.id = n.journal_id', 'inner')->where('j.campaign_id', $campaignId)
            ->where('n.batch_id', $batchId)->where('j.deleted_at', null)->get()->getResultArray();
        return ['targets' => array_map(static function (array $row): array {
            return ['userId' => (int) $row['user_id'], 'notificationId' => (int) $row['id'], 'handoutId' => (int) $row['journal_id'], 'title' => (string) $row['title']];
        }, $rows)];
    }

    public function purgeDeleted(): array
    {
        $threshold = date('Y-m-d H:i:s', time() - 30 * 86400);
        $purged = ['entries' => 0, 'journals' => 0, 'assets' => 0];
        foreach ($this->db->table('handout_library_entries')->where('deleted_at <', $threshold)->get()->getResultArray() as $row) {
            $this->db->table('handout_library_entries')->where('id', $row['id'])->delete();
            $purged['entries']++;
        }
        foreach ($this->db->table('journals')->where('deleted_at <', $threshold)->get()->getResultArray() as $row) {
            $this->db->table('journals')->where('id', $row['id'])->delete();
            $purged['journals']++;
        }
        // Assets only exist in private storage.  An unused upload is retained for
        // the same 30-day grace period as trashed entries, then is collectible.
        foreach ($this->db->table('handout_assets')
            ->groupStart()->where('deleted_at <', $threshold)->orGroupStart()
                ->where('deleted_at', null)->where('created_at <', $threshold)->groupEnd()->groupEnd()
            ->get()->getResultArray() as $asset) {
            if ($this->db->table('handout_asset_references')->where('asset_id', $asset['id'])->countAllResults()) continue;
            $this->storage->remove((string) $asset['storage_key']);
            $this->db->table('handout_assets')->where('id', $asset['id'])->delete();
            $purged['assets']++;
        }
        return $purged;
    }

    private function libraryEntryPayload(int $ownerId, array $payload, bool $partial, ?array $existing = null): array
    {
        $title = array_key_exists('title', $payload) ? $this->name($payload['title'], 180, 'title') : ($existing['title'] ?? null);
        $rawContent = array_key_exists('content', $payload) ? $payload['content'] : ($existing['content_json'] ?? null);
        $content = $this->content->validate($rawContent, false);
        if (!$content['valid']) throw new CampaignException('validation_failed', 'Library content is invalid.', 422, $content['errors']);
        $folderId = array_key_exists('folderId', $payload) || array_key_exists('folder_id', $payload)
            ? $this->idOrNull($payload['folderId'] ?? $payload['folder_id']) : ($existing['folder_id'] ?? null);
        if ($folderId !== null) $this->folder($ownerId, (int) $folderId, false);
        $tagIds = array_key_exists('tagIds', $payload) || array_key_exists('tag_ids', $payload)
            ? $this->ownedTagIds($ownerId, $payload['tagIds'] ?? $payload['tag_ids']) : ($existing ? $this->tagIdsForEntry((int) $existing['id']) : []);
        return [
            'data' => ['title' => $title, 'folder_id' => $folderId, 'content_json' => $content['json'], 'search_text' => $content['searchText']],
            'assetIds' => $content['assetIds'], 'tagIds' => $tagIds,
        ];
    }

    private function presentLibraryEntry(array $entry, bool $detail): array
    {
        $result = [
            'id' => (int) $entry['id'], 'folderId' => $entry['folder_id'] === null ? null : (int) $entry['folder_id'],
            'title' => (string) $entry['title'], 'revision' => (int) $entry['revision'],
            'createdAt' => $entry['created_at'], 'updatedAt' => $entry['updated_at'], 'deletedAt' => $entry['deleted_at'] ?? null,
            'tags' => $this->tagsForEntry((int) $entry['id']),
        ];
        if ($detail) {
            $result['content'] = $entry['content_json'];
            $result['assets'] = $this->assetsForContext('library', (int) $entry['id']);
        }
        return $result;
    }

    private function presentJournal(array $context, array $journal, bool $detail): array
    {
        $result = [
            'id' => (int) $journal['id'], 'sourceLibraryEntryId' => $journal['source_library_entry_id'] === null ? null : (int) $journal['source_library_entry_id'],
            'authorUserId' => (int) $journal['author_user_id'], 'title' => (string) $journal['title'],
            'tags' => array_values((array) ($journal['tag_snapshot_json'] ?? [])), 'folderPath' => $journal['folder_path_snapshot'] ?? null,
            'audienceMode' => (string) $journal['audience_mode'], 'revision' => (int) $journal['revision'],
            'publishedAt' => $journal['published_at'], 'updatedAt' => $journal['updated_at'], 'deletedAt' => $journal['deleted_at'] ?? null,
            'canEdit' => $this->access->canEdit($context, $journal), 'canPresent' => $this->access->canPresent($context),
        ];
        if ($detail) {
            $result['content'] = $this->visibleJournalContent($context, $journal);
            $result['assets'] = $this->assetsForContext('campaign', (int) $journal['id']);
            $result['recipients'] = !empty($context['capabilities']['canManage']) ? $this->recipients((int) $journal['id']) : [];
            $result['links'] = $this->visibleJournalLinks($context, $journal);
        }
        return $result;
    }

    private function libraryFolders(int $ownerId, bool $trash): array
    {
        $query = $this->db->table('handout_library_folders')->where('owner_user_id', $ownerId);
        $trash ? $query->where('deleted_at IS NOT NULL', null, false) : $query->where('deleted_at', null);
        return array_map(function (array $folder): array {
            return ['id' => (int) $folder['id'], 'parentId' => $folder['parent_id'] === null ? null : (int) $folder['parent_id'], 'name' => $folder['name'], 'sortOrder' => (int) $folder['sort_order'], 'deletedAt' => $folder['deleted_at'] ?? null];
        }, $query->orderBy('sort_order', 'ASC')->orderBy('name', 'ASC')->get()->getResultArray());
    }

    private function libraryTags(int $ownerId): array
    {
        return array_map([$this, 'presentTag'], $this->tags->where('owner_user_id', $ownerId)->orderBy('name', 'ASC')->findAll());
    }

    private function presentTag(?array $tag): array
    {
        return ['id' => (int) $tag['id'], 'name' => (string) $tag['name']];
    }

    private function libraryEntry(int $ownerId, int $entryId, bool $includeDeleted): array
    {
        $query = $this->entries->where('id', $entryId)->where('owner_user_id', $ownerId);
        if (!$includeDeleted) $query->where('deleted_at', null);
        $entry = $query->first();
        if (!$entry) throw new CampaignException('handout_library_entry_not_found', 'Library handout was not found.', 404);
        return $entry;
    }

    private function folder(int $ownerId, int $folderId, bool $includeDeleted): array
    {
        $query = $this->folders->where('id', $folderId)->where('owner_user_id', $ownerId);
        if (!$includeDeleted) $query->where('deleted_at', null);
        $folder = $query->first();
        if (!$folder) throw new CampaignException('handout_folder_not_found', 'Handout folder was not found.', 404);
        return $folder;
    }

    private function journal(int $campaignId, int $journalId, bool $includeDeleted): array
    {
        $query = $this->journals->where('id', $journalId)->where('campaign_id', $campaignId)->where('kind', 'handout');
        if (!$includeDeleted) $query->where('deleted_at', null);
        $journal = $query->first();
        if (!$journal) throw new CampaignException('handout_not_found', 'Handout was not found.', 404);
        return $journal;
    }

    private function ownedTagIds(int $ownerId, $values): array
    {
        if (!is_array($values) || count($values) > 30) {
            throw new CampaignException('validation_failed', 'Tags are invalid.', 422, ['tagIds' => 'Choose at most 30 tags.']);
        }
        $ids = [];
        foreach ($values as $value) $ids[] = $this->requiredId($value, 'tagIds');
        $ids = array_values(array_unique($ids));
        if (!$ids) return [];
        $found = $this->tags->where('owner_user_id', $ownerId)->whereIn('id', $ids)->findAll();
        if (count($found) !== count($ids)) throw new CampaignException('validation_failed', 'Tag is outside your library.', 422, ['tagIds' => 'Tag is invalid.']);
        return $ids;
    }

    private function tagIdsForEntry(int $entryId): array
    {
        return array_map('intval', array_column($this->db->table('handout_library_entry_tags')->select('tag_id')->where('entry_id', $entryId)->get()->getResultArray(), 'tag_id'));
    }

    private function tagsForEntry(int $entryId): array
    {
        return array_values(array_map('strval', array_column($this->db->table('handout_library_entry_tags et')
            ->select('t.name')->join('handout_library_tags t', 't.id = et.tag_id', 'inner')->where('et.entry_id', $entryId)
            ->orderBy('t.name', 'ASC')->get()->getResultArray(), 'name')));
    }

    private function syncEntryTags(int $entryId, array $tagIds): void
    {
        $this->db->table('handout_library_entry_tags')->where('entry_id', $entryId)->delete();
        foreach ($tagIds as $tagId) $this->db->table('handout_library_entry_tags')->insert(['entry_id' => $entryId, 'tag_id' => $tagId]);
    }

    private function syncAssetReferences(int $ownerId, array $assetIds, string $type, int $contextId, bool $allowExistingContextAssets = false): void
    {
        $assetIds = array_values(array_unique(array_map('intval', $assetIds)));
        if ($assetIds) {
            $found = $this->assets->where('deleted_at', null)->whereIn('id', $assetIds)->findAll();
            $permitted = [];
            foreach ($found as $asset) {
                $assetId = (int) $asset['id'];
                if ((int) $asset['owner_user_id'] === $ownerId) {
                    $permitted[$assetId] = true;
                    continue;
                }
                if ($allowExistingContextAssets && $this->db->table('handout_asset_references')
                    ->where('asset_id', $assetId)->where('context_type', $type)->where('context_id', $contextId)->countAllResults()) {
                    $permitted[$assetId] = true;
                }
            }
            if (count($permitted) !== count($assetIds)) {
                throw new CampaignException('validation_failed', 'Image asset is unavailable.', 422, ['content' => 'Image asset is unavailable.']);
            }
        }
        $this->removeAssetReferences($type, $contextId);
        foreach ($assetIds as $assetId) $this->db->table('handout_asset_references')->insert(['asset_id' => $assetId, 'context_type' => $type, 'context_id' => $contextId, 'created_at' => $this->now()]);
    }

    private function removeAssetReferences(string $type, int $contextId): void
    {
        $this->db->table('handout_asset_references')->where('context_type', $type)->where('context_id', $contextId)->delete();
    }

    private function assetsForContext(string $type, int $contextId): array
    {
        return array_map(function (array $asset): array { return $this->presentAsset($asset); }, $this->db->table('handout_asset_references r')
            ->select('a.*')->join('handout_assets a', 'a.id = r.asset_id', 'inner')->where('r.context_type', $type)
            ->where('r.context_id', $contextId)->where('a.deleted_at', null)->get()->getResultArray());
    }

    private function presentAsset(array $asset): array
    {
        return ['id' => (int) $asset['id'], 'name' => (string) $asset['original_name'], 'mimeType' => (string) $asset['mime_type'], 'byteSize' => (int) $asset['byte_size']];
    }

    private function folderPath(int $ownerId, $folderId): ?string
    {
        if ($folderId === null) return null;
        $parts = [];
        $seen = [];
        while ($folderId !== null) {
            $id = (int) $folderId;
            if (isset($seen[$id])) break;
            $seen[$id] = true;
            $folder = $this->folders->where('id', $id)->where('owner_user_id', $ownerId)->first();
            if (!$folder) break;
            array_unshift($parts, (string) $folder['name']);
            $folderId = $folder['parent_id'];
        }
        return $parts ? implode(' / ', $parts) : null;
    }

    private function folderDescendsFrom(int $ownerId, int $folderId, int $ancestorId): bool
    {
        $seen = [];
        while ($folderId > 0 && !isset($seen[$folderId])) {
            if ($folderId === $ancestorId) return true;
            $seen[$folderId] = true;
            $folder = $this->folders->where('id', $folderId)->where('owner_user_id', $ownerId)->first();
            if (!$folder || $folder['parent_id'] === null) return false;
            $folderId = (int) $folder['parent_id'];
        }
        return false;
    }

    private function relatedLinks($value): array
    {
        if (!is_array($value) || count($value) > 50) throw new CampaignException('validation_failed', 'Related links are invalid.', 422, ['relatedLinks' => 'Use at most 50 related links.']);
        $links = [];
        foreach ($value as $link) {
            if (!is_array($link)) throw new CampaignException('validation_failed', 'Related link is invalid.', 422, ['relatedLinks' => 'Link is invalid.']);
            $type = strtolower(trim((string) ($link['targetType'] ?? $link['target_type'] ?? '')));
            $id = $this->requiredId($link['targetId'] ?? $link['target_id'] ?? null, 'relatedLinks');
            $label = $this->name($link['label'] ?? '', 180, 'relatedLinks');
            if (!in_array($type, ['scene', 'character', 'item'], true)) throw new CampaignException('validation_failed', 'Related link type is invalid.', 422, ['relatedLinks' => 'Link type is invalid.']);
            $links[$type . ':' . $id] = ['targetType' => $type, 'targetId' => $id, 'label' => $label];
        }
        return array_values($links);
    }

    private function relatedLinksForJournal(int $journalId): array
    {
        return array_map(static function (array $link): array {
            return [
                'targetType' => (string) $link['target_type'],
                'targetId' => (int) $link['target_id'],
                'label' => (string) $link['label'],
            ];
        }, $this->db->table('journal_links')->where('journal_id', $journalId)
            ->where('placement', 'related')->get()->getResultArray());
    }

    private function validateCampaignTarget(array $auth, int $campaignId, string $type, int $id): void
    {
        $this->permissions->requireLevel($auth, $campaignId, $type, $id, AccessLevel::LIMITED);
    }

    private function syncJournalLinks(int $journalId, array $mentions, array $related): void
    {
        $this->db->table('journal_links')->where('journal_id', $journalId)->delete();
        foreach ([['links' => $mentions, 'placement' => 'mention'], ['links' => $related, 'placement' => 'related']] as $group) {
            foreach ($group['links'] as $link) $this->db->table('journal_links')->insert([
                'journal_id' => $journalId, 'target_type' => $link['targetType'], 'target_id' => $link['targetId'],
                'placement' => $group['placement'], 'label' => $link['label'], 'created_at' => $this->now(),
            ]);
        }
    }

    private function visibleJournalLinks(array $context, array $journal): array
    {
        $rows = $this->db->table('journal_links')->where('journal_id', (int) $journal['id'])->orderBy('placement', 'ASC')->orderBy('id', 'ASC')->get()->getResultArray();
        $links = [];
        foreach ($rows as $row) {
            try {
                $level = $this->permissions->levelFor($context['auth'], (int) $journal['campaign_id'], (string) $row['target_type'], (int) $row['target_id']);
                if (!AccessLevel::allows($level, AccessLevel::LIMITED)) continue;
            } catch (CampaignException $exception) {
                continue;
            }
            $links[] = ['targetType' => $row['target_type'], 'targetId' => (int) $row['target_id'], 'placement' => $row['placement'], 'label' => $row['label']];
        }
        return $links;
    }

    /**
     * A mention stores its label in the document, so hiding only the relations
     * sidebar would still disclose the target's name. Replace an inaccessible
     * mention with a neutral marker before handing the JSON to a player.
     */
    private function visibleJournalContent(array $context, array $journal): array
    {
        $content = is_array($journal['content_json'] ?? null)
            ? $journal['content_json']
            : ['type' => 'doc', 'content' => []];
        if (!empty($context['capabilities']['canManage'])) return $content;
        return $this->redactHiddenMentions($content, $context, (int) $journal['campaign_id'], null);
    }

    private function redactHiddenMentions(array $node, array $context, int $campaignId, ?string $parent): array
    {
        if (($node['type'] ?? null) === 'mention') {
            try {
                $level = $this->permissions->levelFor(
                    $context['auth'],
                    $campaignId,
                    (string) (($node['attrs'] ?? [])['targetType'] ?? ''),
                    (int) (($node['attrs'] ?? [])['targetId'] ?? 0)
                );
                if (AccessLevel::allows($level, AccessLevel::LIMITED)) return $node;
            } catch (CampaignException $exception) {
                // A missing or inaccessible target is equally redacted.
            }
            return $parent === 'doc'
                ? ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '@']]]
                : ['type' => 'text', 'text' => '@'];
        }
        if (!is_array($node['content'] ?? null)) return $node;
        $copy = $node;
        $copy['content'] = array_map(function (array $child) use ($context, $campaignId, $node): array {
            return $this->redactHiddenMentions($child, $context, $campaignId, (string) ($node['type'] ?? ''));
        }, $node['content']);
        return $copy;
    }

    private function recipients(int $journalId): array
    {
        return array_map('intval', array_column($this->db->table('journal_recipients')->select('user_id')->where('journal_id', $journalId)->get()->getResultArray(), 'user_id'));
    }

    private function activeRecipients(int $campaignId, $values, bool $strict = false, bool $all = false): array
    {
        $rows = $this->db->table('campaign_members')->select('user_id')->where('campaign_id', $campaignId)->where('is_active', 1)->get()->getResultArray();
        $active = array_map('intval', array_column($rows, 'user_id'));
        if ($all) return $active;
        if (!is_array($values) || count($values) > 200) throw new CampaignException('validation_failed', 'Recipients are invalid.', 422, ['recipientIds' => 'Recipients are invalid.']);
        $ids = array_values(array_unique(array_map(function ($value): int { return $this->requiredId($value, 'recipientIds'); }, $values)));
        if ($strict && count(array_diff($ids, $active))) throw new CampaignException('validation_failed', 'Recipient is not an active campaign member.', 422, ['recipientIds' => 'Recipient is invalid.']);
        return array_values(array_intersect($ids, $active));
    }

    private function upsertNotifications(int $journalId, array $recipientIds, string $batchId, int $actorId): void
    {
        foreach ($recipientIds as $recipientId) {
            if ($recipientId === $actorId) continue;
            $now = $this->now();
            $existing = $this->db->table('journal_notifications')->where('journal_id', $journalId)->where('user_id', $recipientId)->get()->getRowArray();
            if ($existing) {
                $this->db->table('journal_notifications')->where('id', $existing['id'])->update(['batch_id' => $batchId, 'read_at' => null, 'updated_at' => $now]);
            } else {
                $this->db->table('journal_notifications')->insert(['journal_id' => $journalId, 'user_id' => $recipientId, 'batch_id' => $batchId, 'read_at' => null, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    private function isActiveLeader(int $campaignId, int $userId): bool
    {
        $campaign = $this->db->table('campaigns')->where('id', $campaignId)->get()->getRowArray();
        if ($campaign && (int) $campaign['game_master_id'] === $userId) return true;
        return (bool) $this->db->table('campaign_members')->where('campaign_id', $campaignId)->where('user_id', $userId)
            ->where('is_active', 1)->whereIn('role', ['gm', 'assistant'])->countAllResults();
    }

    private function canViewReferencedAsset(array $auth, int $assetId): bool
    {
        $refs = $this->db->table('handout_asset_references')->where('asset_id', $assetId)->get()->getResultArray();
        foreach ($refs as $ref) {
            if ($ref['context_type'] !== 'campaign') continue;
            $journal = $this->db->table('journals')->where('id', $ref['context_id'])->where('deleted_at', null)->get()->getRowArray();
            if (!$journal) continue;
            try {
                $context = $this->access->campaign($auth, (int) $journal['campaign_id']);
                if ($this->access->canView($context, $journal)) return true;
            } catch (CampaignException $exception) {
                continue;
            }
        }
        return false;
    }

    private function tagNames($value): array
    {
        if (!is_array($value) || count($value) > 30) throw new CampaignException('validation_failed', 'Tags are invalid.', 422, ['tags' => 'Use at most 30 tags.']);
        $names = [];
        foreach ($value as $tag) $names[(string) $this->name($tag, 80, 'tags')] = true;
        return array_keys($names);
    }

    private function requiredId($value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CampaignException('validation_failed', 'Identifier is invalid.', 422, [$field => 'Identifier must be positive.']);
        return (int) $id;
    }

    private function idOrNull($value): ?int
    {
        if ($value === null || $value === '') return null;
        return $this->requiredId($value, 'id');
    }

    private function name($value, int $max, string $field): string
    {
        $name = trim((string) $value);
        if ($name === '' || mb_strlen($name) > $max || preg_match('/[\x00-\x1F\x7F]/u', $name)) {
            throw new CampaignException('validation_failed', 'Text is invalid.', 422, [$field => 'Text is required and too long.']);
        }
        return $name;
    }

    private function revision(array $payload): int
    {
        return $this->requiredId($payload['revision'] ?? null, 'revision');
    }

    private function limit($value): int
    {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        return $value === false ? 30 : max(1, min(50, (int) $value));
    }

    private function truthy($value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }

    private function commit(string $code): void
    {
        if ($this->db->transStatus() === false || !$this->db->transCommit()) {
            throw new CampaignException($code, 'Handout could not be saved.', 500);
        }
    }

    private function conflict(int $currentRevision): void
    {
        throw new CampaignException('revision_conflict', 'Handout changed since it was loaded.', 409, ['currentRevision' => $currentRevision]);
    }

    private function conflictForEntry(int $ownerId, int $entryId): void
    {
        $entry = $this->libraryEntry($ownerId, $entryId, true);
        $this->conflict((int) $entry['revision']);
    }

    private function conflictForJournal(int $campaignId, int $journalId): void
    {
        $journal = $this->journal($campaignId, $journalId, true);
        $this->conflict((int) $journal['revision']);
    }
}
