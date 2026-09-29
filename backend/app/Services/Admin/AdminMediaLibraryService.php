<?php

namespace App\Services\Admin;

use App\Models\MediaAssetModel;
use App\Models\MediaCollectionModel;
use App\Services\CharacterAssetService;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;

final class AdminMediaLibraryService
{
    private $db;
    private $assets;
    private $collections;
    private $media;
    private $relations;

    public function __construct(?BaseConnection $db = null, ?MediaService $media = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->assets = new MediaAssetModel($this->db);
        $this->collections = new MediaCollectionModel($this->db);
        $this->media = $media ?: new MediaService($this->db);
        $this->relations = new MediaAssetRelationshipResolver($this->db);
    }

    public function list(array $auth, array $filters): array
    {
        $this->verifiedAdmin($auth);
        $this->assertSchema();
        $page = $this->integer($filters['page'] ?? 1, 1, 100000, 'page');
        $perPage = $this->integer($filters['perPage'] ?? $filters['pageSize'] ?? 30, 1, 100, 'perPage');
        $sortMap = [
            'id' => 'media_assets.id', 'name' => 'media_assets.name',
            'createdAt' => 'media_assets.created_at', 'updatedAt' => 'media_assets.updated_at',
            'fileSize' => 'media_assets.file_size', 'format' => 'media_assets.format',
        ];
        $sort = (string) ($filters['sort'] ?? 'createdAt');
        if (!isset($sortMap[$sort])) {
            throw new AdminException('validation_failed', 'Media sort field is invalid.', 422, ['sort' => 'invalid']);
        }
        $direction = strtolower((string) ($filters['direction'] ?? 'desc'));
        if (!in_array($direction, ['asc', 'desc'], true)) {
            throw new AdminException('validation_failed', 'Media sort direction is invalid.', 422, ['direction' => 'invalid']);
        }

        $builder = $this->db->table('media_assets')
            ->select('media_assets.*, users.username AS owner_username')
            ->join('users', 'users.id = media_assets.owner_user_id', 'left')
            ->where('media_assets.deleted_at', null);
        $this->applyFilters($builder, $filters);
        $total = $builder->countAllResults(false);
        $rows = $builder->orderBy($sortMap[$sort], strtoupper($direction))
            ->orderBy('media_assets.id', 'DESC')
            ->limit($perPage, ($page - 1) * $perPage)->get()->getResultArray();
        $assigned = array_fill_keys($this->relations->assignedIds(), true);
        $items = array_map(fn (array $row): array => $this->summary($row, isset($assigned[(int) $row['id']])), $rows);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
            'facets' => $this->facets($assigned),
        ];
    }

    /**
     * Returns a small, searchable page of personal audio libraries. This stays
     * separate from the asset-list payload so opening the Audio category does
     * not serialize every Game Master's library.
     */
    public function audioLibraries(array $auth, array $filters): array
    {
        $this->verifiedAdmin($auth);
        if (!$this->db->tableExists('audio_libraries') || !$this->db->tableExists('audio_tracks')) {
            return [
                'items' => [],
                'pagination' => ['page' => 1, 'perPage' => 25, 'total' => 0, 'pages' => 1],
            ];
        }

        $page = $this->integer($filters['page'] ?? 1, 1, 100000, 'page');
        $perPage = $this->integer($filters['perPage'] ?? 25, 1, 50, 'perPage');
        $query = trim((string) ($filters['q'] ?? ''));
        if (mb_strlen($query) > 120) {
            throw new AdminException('validation_failed', 'Audio library search query is too long.', 422, ['q' => 'max_length']);
        }

        $builder = $this->db->table('audio_libraries libraries')
            ->select(
                'libraries.id, libraries.name, libraries.owner_user_id, users.username AS owner_username, '
                . '(SELECT COUNT(*) FROM audio_tracks tracks '
                . 'WHERE tracks.library_id = libraries.id AND tracks.deleted_at IS NULL) AS track_count, '
                . '(SELECT COUNT(*) FROM audio_tracks tracks '
                . 'WHERE tracks.library_id = libraries.id AND tracks.media_asset_id IS NOT NULL '
                . 'AND tracks.deleted_at IS NULL) AS asset_count',
                false
            )
            ->join('users', 'users.id = libraries.owner_user_id', 'left')
            ->where('libraries.scope', 'personal');
        if ($query !== '') {
            $builder->groupStart()
                ->like('users.username', $query)
                ->orLike('libraries.name', $query)
                ->groupEnd();
        }

        $total = (clone $builder)->countAllResults();
        $rows = $builder->orderBy('users.username', 'ASC')
            ->orderBy('libraries.name', 'ASC')
            ->orderBy('libraries.id', 'ASC')
            ->limit($perPage, ($page - 1) * $perPage)
            ->get()->getResultArray();

        return [
            'items' => array_map([$this, 'presentAudioLibrary'], $rows),
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'pages' => max(1, (int) ceil($total / $perPage)),
            ],
        ];
    }

    public function detail(array $auth, int $assetId): array
    {
        $this->verifiedAdmin($auth);
        $asset = $this->asset($assetId);
        $presented = $this->media->get($auth, $assetId);
        $owner = null;
        if (!empty($asset['owner_user_id'])) {
            $owner = $this->db->table('users')->select('id, username, email')
                ->where('id', (int) $asset['owner_user_id'])->get()->getRowArray();
        }
        return ['asset' => $this->detailPayload($asset, $presented, $owner)];
    }

    public function upload(array $auth, string $path, array $input): array
    {
        $this->verifiedAdmin($auth);
        $uploaded = $this->media->uploadFile($auth, $path, $input);
        return $this->detail($auth, (int) $uploaded['id']);
    }

    public function registerExternal(array $auth, array $input): array
    {
        $this->verifiedAdmin($auth);
        $registered = $this->media->registerExternalUrl($auth, $input);
        return $this->detail($auth, (int) $registered['id']);
    }

    /**
     * Publishes a private Game Master track in an existing setting library.
     * A copy creates a second audio-track record over the same media asset;
     * a move keeps the track ID and all campaign/playlist references intact.
     */
    public function publishPersonalAudioTrack(array $auth, int $trackId, array $input): array
    {
        $this->verifiedAdmin($auth);
        if (!$this->db->tableExists('audio_tracks') || !$this->db->tableExists('audio_libraries')) {
            throw new AdminException('audio_library_unavailable', 'The audio library schema is unavailable.', 503);
        }
        $mode = strtolower(trim((string) ($input['mode'] ?? 'copy')));
        if (!in_array($mode, ['copy', 'move'], true)) {
            throw new AdminException('validation_failed', 'Audio publication mode is invalid.', 422, ['mode' => 'invalid']);
        }
        $targetLibraryId = $this->integer(
            $input['targetLibraryId'] ?? $input['target_library_id'] ?? null,
            1,
            PHP_INT_MAX,
            'targetLibraryId'
        );
        $source = $this->audioTrackRow($trackId);
        if (($source['library_scope'] ?? null) !== 'personal') {
            throw new AdminException(
                'audio_track_not_personal',
                'Only a Game Master personal audio track can be published.',
                409
            );
        }
        $target = $this->db->table('audio_libraries libraries')
            ->select('libraries.id, libraries.name, libraries.scope, libraries.setting_id, libraries.system_id')
            ->where('libraries.id', $targetLibraryId)
            ->where('libraries.scope', 'system')
            ->get()->getRowArray();
        if (!$target) {
            throw new AdminException('audio_library_not_found', 'The target setting audio library was not found.', 404);
        }
        if ($mode === 'copy' && empty($source['media_asset_id'])
            && ($source['source_type'] ?? '') !== 'external') {
            throw new AdminException(
                'audio_track_requires_media_asset',
                'This legacy audio track must be migrated to the media library before copying.',
                409
            );
        }

        $this->db->transBegin();
        try {
            if ($mode === 'move') {
                $publishedTrackId = $trackId;
                $this->db->table('audio_tracks')->where('id', $trackId)->update([
                    'library_id' => (int) $target['id'],
                    'owner_user_id' => null,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            } else {
                $copy = $this->audioTrackCopy($source, (int) $target['id']);
                if (!$this->db->table('audio_tracks')->insert($copy)) {
                    throw new \RuntimeException('Audio track copy failed.');
                }
                $publishedTrackId = (int) $this->db->insertID();
            }
            if ($this->db->transStatus() === false) {
                throw new \RuntimeException('Audio track publication failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof AdminException) {
                throw $exception;
            }
            throw new AdminException('audio_track_publish_failed', 'The audio track could not be published.', 500);
        }

        return [
            'mode' => $mode,
            'sourceTrackId' => $trackId,
            'trackId' => $publishedTrackId,
            'mediaAssetId' => !empty($source['media_asset_id']) ? (int) $source['media_asset_id'] : null,
            'targetLibrary' => $this->presentAudioLibrary($target),
        ];
    }

    public function update(array $auth, int $assetId, array $input): array
    {
        $this->verifiedAdmin($auth);
        $asset = $this->asset($assetId);
        $expectedRevision = $this->integer($input['revision'] ?? 0, 1, PHP_INT_MAX, 'revision');
        if ((int) ($asset['revision'] ?? 1) !== $expectedRevision) {
            throw new AdminException('revision_conflict', 'The asset was changed by another request.', 409, [
                'currentRevision' => (int) ($asset['revision'] ?? 1),
            ]);
        }
        $update = $this->editableFields($input, false);
        $visibility = $update['visibility'] ?? (string) $asset['visibility'];
        $campaignId = array_key_exists('campaign_id', $update) ? $update['campaign_id'] : ($asset['campaign_id'] ?? null);
        if (($asset['provider'] ?? '') === 'external' && $visibility !== 'public') {
            throw new AdminException(
                'external_asset_visibility_invalid',
                'External URL references must remain public catalogue assets.',
                422,
                ['visibility' => 'external_assets_are_public']
            );
        }
        $this->assertRelationInvariants($asset, $visibility, $campaignId);
        if ($visibility !== (string) $asset['visibility']
            || (int) ($campaignId ?? 0) !== (int) ($asset['campaign_id'] ?? 0)) {
            return $this->relocate($auth, $asset, $update);
        }
        $update['revision'] = $expectedRevision + 1;
        if (!$this->assets->update($assetId, $update)) {
            throw new AdminException('media_write_failed', 'The asset could not be updated.', 500, $this->assets->errors());
        }
        return $this->detail($auth, $assetId);
    }

    public function bulk(array $auth, array $input): array
    {
        $this->verifiedAdmin($auth);
        $ids = $this->ids($input['ids'] ?? []);
        if (!$ids || count($ids) > 100) {
            throw new AdminException('validation_failed', 'Bulk operations require between 1 and 100 asset IDs.', 422);
        }
        if (array_key_exists('metadata', $input)
            && (!is_array($input['metadata']) || array_is_list($input['metadata']))) {
            throw new AdminException('validation_failed', 'Bulk metadata must be a JSON object.', 422);
        }
        $results = [];
        foreach ($ids as $id) {
            try {
                $asset = $this->asset($id);
                $payload = $input;
                $payload['revision'] = (int) ($asset['revision'] ?? 1);
                $payload = $this->applyBulkTags($asset, $payload);
                if (isset($input['metadata']) && is_array($input['metadata'])) {
                    $payload['customMetadata'] = array_merge(
                        is_array($asset['custom_metadata'] ?? null) ? $asset['custom_metadata'] : [],
                        $input['metadata']
                    );
                }
                $updated = $this->update($auth, $id, $payload);
                $results[] = ['id' => $id, 'ok' => true, 'asset' => $updated['asset']];
            } catch (AdminException $exception) {
                $results[] = ['id' => $id, 'ok' => false, 'code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            } catch (MediaException $exception) {
                $results[] = ['id' => $id, 'ok' => false, 'code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            }
        }
        return ['results' => $results];
    }

    public function delete(array $auth, int $assetId): array
    {
        $this->verifiedAdmin($auth);
        $this->asset($assetId);
        $relations = $this->relations->forAsset($assetId);
        if ($relations) {
            throw new AdminException('asset_in_use', 'The asset is still used by application modules.', 409, [
                'relations' => $relations,
            ]);
        }
        try {
            return ['asset' => $this->media->purge($assetId)];
        } catch (MediaException $exception) {
            throw $exception;
        }
    }

    public function retryPurge(array $auth, int $assetId): array
    {
        $this->verifiedAdmin($auth);
        $asset = $this->asset($assetId);
        if (($asset['status'] ?? '') !== 'delete_failed') {
            throw new AdminException('invalid_asset_status', 'Only a failed purge can be retried.', 409);
        }
        if ($this->relations->forAsset($assetId)) {
            throw new AdminException('asset_in_use', 'The asset is still used by application modules.', 409);
        }
        return ['asset' => $this->media->purge($assetId)];
    }

    public function initiateReplacement(array $auth, int $assetId, array $input): array
    {
        $this->verifiedAdmin($auth);
        $asset = $this->asset($assetId);
        if (($asset['status'] ?? '') !== 'ready') {
            throw new AdminException('invalid_asset_status', 'Only a ready asset can be replaced.', 409);
        }
        $input['category'] = $asset['category'];
        $input['visibility'] = $asset['visibility'];
        $input['campaignId'] = $asset['campaign_id'];
        $result = $this->media->initiateUpload($auth, $input);
        return ['targetId' => $assetId] + $result;
    }

    public function completeReplacement(array $auth, int $assetId, int $stagingId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $target = $this->asset($assetId);
        $this->media->completeUpload($auth, $stagingId, $payload);
        return $this->finalizeReplacement($auth, $target, $this->asset($stagingId));
    }

    public function collections(array $auth): array
    {
        $this->verifiedAdmin($auth);
        $rows = $this->db->table('media_collections collection')
            ->select('collection.*, users.username AS owner_username, COUNT(link.media_asset_id) AS asset_count')
            ->join('users', 'users.id = collection.owner_user_id', 'left')
            ->join('media_collection_assets link', 'link.collection_id = collection.id', 'left')
            ->groupBy('collection.id')->orderBy('collection.name', 'ASC')->get()->getResultArray();
        return ['items' => array_map([$this, 'presentCollection'], $rows)];
    }

    public function createCollection(array $auth, array $input): array
    {
        $admin = $this->verifiedAdmin($auth);
        $row = $this->collectionFields($input, (int) $admin['id']);
        if (!$this->collections->insert($row)) {
            throw new AdminException('media_collection_write_failed', 'The collection could not be created.', 422, $this->collections->errors());
        }
        return ['collection' => $this->presentCollection($this->collections->find($this->collections->getInsertID()))];
    }

    public function updateCollection(array $auth, int $collectionId, array $input): array
    {
        $this->verifiedAdmin($auth);
        $this->collection($collectionId);
        $row = $this->collectionFields($input, null);
        if (!$this->collections->update($collectionId, $row)) {
            throw new AdminException('media_collection_write_failed', 'The collection could not be updated.', 422, $this->collections->errors());
        }
        return ['collection' => $this->presentCollection($this->collections->find($collectionId))];
    }

    public function deleteCollection(array $auth, int $collectionId): array
    {
        $this->verifiedAdmin($auth);
        $this->collection($collectionId);
        $this->collections->delete($collectionId);
        return ['id' => $collectionId, 'deleted' => true];
    }

    public function changeCollectionAssets(array $auth, int $collectionId, array $input, bool $add): array
    {
        $admin = $this->verifiedAdmin($auth);
        $this->collection($collectionId);
        $ids = $this->ids($input['ids'] ?? []);
        if (!$ids || count($ids) > 100) {
            throw new AdminException('validation_failed', 'Choose between 1 and 100 assets.', 422);
        }
        foreach ($ids as $id) {
            $this->asset($id);
            if ($add) {
                $this->db->query(
                    'INSERT IGNORE INTO media_collection_assets (collection_id, media_asset_id, added_by_user_id, created_at) VALUES (?, ?, ?, ?)',
                    [$collectionId, $id, (int) $admin['id'], date('Y-m-d H:i:s')]
                );
            } else {
                $this->db->table('media_collection_assets')->where('collection_id', $collectionId)
                    ->where('media_asset_id', $id)->delete();
            }
        }
        return ['collectionId' => $collectionId, 'ids' => $ids, 'mode' => $add ? 'add' : 'remove'];
    }

    public function createCharacterSet(array $auth, array $input): array
    {
        $this->verifiedAdmin($auth);
        $slots = is_array($input['slots'] ?? null) ? $input['slots'] : [];
        if (array_diff(['avatar', 'portrait', 'token', 'fullbody'], array_keys($slots))) {
            throw new AdminException('validation_failed', 'All four character asset slots are required.', 422);
        }
        try {
            $set = (new CharacterAssetService())->createAvailableSet(
                (string) ($input['name'] ?? ''), [], $slots, $auth
            );
        } catch (\InvalidArgumentException $exception) {
            throw new AdminException('validation_failed', $exception->getMessage(), 422);
        }
        return ['assetSet' => $set];
    }

    private function finalizeReplacement(array $auth, array $target, array $staging): array
    {
        if (($staging['status'] ?? '') !== 'ready') {
            throw new AdminException('replacement_not_ready', 'The replacement upload is not ready.', 409);
        }
        if (($target['resource_type'] ?? '') !== ($staging['resource_type'] ?? '')) {
            throw new AdminException('replacement_type_mismatch', 'Replacement resource type must match the original.', 422);
        }
        $targetId = (int) $target['id'];
        $stagingId = (int) $staging['id'];
        $technical = [
            'provider', 'provider_container', 'provider_asset_id', 'public_id', 'resource_type',
            'original_filename', 'mime_type', 'format', 'file_size', 'width', 'height',
            'duration', 'visibility', 'campaign_id', 'metadata',
        ];
        if ($this->db->fieldExists('source_url', 'media_assets')) {
            $technical = array_merge($technical, [
                'source_url', 'availability_status', 'availability_checked_at',
            ]);
        }
        $newTarget = [];
        $oldStaging = [];
        foreach ($technical as $field) {
            $newTarget[$field] = $staging[$field] ?? null;
            $oldStaging[$field] = $target[$field] ?? null;
        }
        foreach (['metadata'] as $jsonField) {
            if (is_array($newTarget[$jsonField] ?? null)) {
                $newTarget[$jsonField] = json_encode($newTarget[$jsonField], JSON_UNESCAPED_UNICODE);
            }
            if (is_array($oldStaging[$jsonField] ?? null)) {
                $oldStaging[$jsonField] = json_encode($oldStaging[$jsonField], JSON_UNESCAPED_UNICODE);
            }
        }
        $newTarget['revision'] = (int) ($target['revision'] ?? 1) + 1;
        $newTarget['status'] = 'ready';
        $oldStaging['status'] = 'ready';

        $this->db->transBegin();
        $this->db->table('media_assets')->where('id', $stagingId)->update([
            'provider_asset_id' => null, 'public_id' => null,
        ]);
        $this->db->table('media_assets')->where('id', $targetId)->update($newTarget);
        $this->db->table('media_assets')->where('id', $stagingId)->update($oldStaging);
        if ($this->db->transStatus() === false) {
            $this->db->transRollback();
            throw new AdminException('replacement_failed', 'The replacement could not be committed.', 500);
        }
        $this->db->transCommit();
        $purgeWarning = null;
        try {
            $this->media->purge($stagingId);
        } catch (MediaException $exception) {
            $purgeWarning = ['code' => $exception->errorCode(), 'message' => $exception->getMessage(), 'stagingId' => $stagingId];
        }
        $result = $this->detail($auth, $targetId);
        if ($purgeWarning !== null) {
            $result['purgeWarning'] = $purgeWarning;
        }
        return $result;
    }

    private function relocate(array $auth, array $asset, array $updates): array
    {
        $presented = $this->media->get($auth, (int) $asset['id']);
        $url = (string) ($presented['url'] ?? '');
        if ($url === '') {
            throw new AdminException('media_provider_unavailable', 'The original object cannot be downloaded for relocation.', 503);
        }
        $temporary = tempnam(sys_get_temp_dir(), 'media-relocate-');
        if ($temporary === false) {
            throw new AdminException('media_relocation_failed', 'Temporary storage is unavailable.', 503);
        }
        if (!$this->assets->update((int) $asset['id'], ['status' => 'relocating'])) {
            @unlink($temporary);
            throw new AdminException('media_relocation_failed', 'The asset could not enter relocation.', 500);
        }
        $stagingId = null;
        try {
            $this->download($url, $temporary);
            $uploaded = $this->media->uploadFile($auth, $temporary, [
                'filename' => $asset['original_filename'] ?: ('asset-' . $asset['id']),
                'mimeType' => $asset['mime_type'],
                'category' => $updates['category'] ?? $asset['category'],
                'visibility' => $updates['visibility'] ?? $asset['visibility'],
                'campaignId' => $updates['campaign_id'] ?? $asset['campaign_id'],
                'ownerUserId' => $asset['owner_user_id'],
            ]);
            $staging = $this->asset((int) $uploaded['id']);
            $stagingId = (int) $staging['id'];
            $this->media->verify($stagingId);
            $result = $this->finalizeReplacement($auth, $asset, $staging);
            $libraryUpdate = array_intersect_key($updates, array_flip(['name', 'description', 'tags', 'category', 'custom_metadata']));
            if ($libraryUpdate) {
                $current = $this->asset((int) $asset['id']);
                $libraryUpdate['revision'] = (int) $current['revision'] + 1;
                $this->assets->update((int) $asset['id'], $libraryUpdate);
                $result = $this->detail($auth, (int) $asset['id']);
            }
            return $result;
        } catch (\Throwable $exception) {
            if ($stagingId !== null) {
                try {
                    $this->media->purge($stagingId);
                } catch (\Throwable $ignored) {
                    // A failed cleanup remains visible as delete_failed for an administrator retry.
                }
            }
            $current = $this->assets->find((int) $asset['id']);
            if (($current['status'] ?? '') === 'relocating') {
                $this->assets->update((int) $asset['id'], ['status' => 'ready']);
            }
            throw $exception;
        } finally {
            @unlink($temporary);
        }
    }

    private function download(string $url, string $path): void
    {
        $output = fopen($path, 'wb');
        if ($output === false || !function_exists('curl_init')) {
            throw new AdminException('media_relocation_failed', 'The original object cannot be downloaded.', 503);
        }
        $handle = curl_init($url);
        curl_setopt_array($handle, [CURLOPT_FILE => $output, CURLOPT_FOLLOWLOCATION => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 300]);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        fclose($output);
        if ($ok === false || $status < 200 || $status >= 300 || filesize($path) < 1) {
            throw new AdminException('media_relocation_failed', 'The original object could not be downloaded.', 503);
        }
    }

    private function applyFilters($builder, array $filters): void
    {
        foreach ([
            'category' => ['media_assets.category', MediaAssetModel::CATEGORIES],
            'resourceType' => ['media_assets.resource_type', ['image', 'audio', 'video', 'document', 'raw']],
            'provider' => ['media_assets.provider', MediaAssetModel::PROVIDERS],
            'visibility' => ['media_assets.visibility', MediaAssetModel::VISIBILITIES],
            'status' => ['media_assets.status', MediaAssetModel::STATUSES],
        ] as $key => [$column, $allowed]) {
            $value = strtolower(trim((string) ($filters[$key] ?? '')));
            if ($value !== '') {
                if (!in_array($value, $allowed, true)) {
                    throw new AdminException('validation_failed', 'Media filter is invalid.', 422, [$key => 'invalid']);
                }
                $builder->where($column, $value);
            }
        }
        foreach (['format' => 'media_assets.format', 'ownerId' => 'media_assets.owner_user_id', 'campaignId' => 'media_assets.campaign_id'] as $key => $column) {
            $value = trim((string) ($filters[$key] ?? ''));
            if ($value !== '') {
                $builder->where($column, $key === 'format' ? strtolower($value) : $this->integer($value, 1, PHP_INT_MAX, $key));
            }
        }
        $collectionId = trim((string) ($filters['collectionId'] ?? ''));
        if ($collectionId !== '') {
            $builder->join('media_collection_assets selected_collection', 'selected_collection.media_asset_id = media_assets.id', 'inner')
                ->where('selected_collection.collection_id', $this->integer($collectionId, 1, PHP_INT_MAX, 'collectionId'));
        }
        $audioLibraryId = trim((string) ($filters['audioLibraryId'] ?? ''));
        if ($audioLibraryId !== '') {
            if (!$this->db->tableExists('audio_tracks')) {
                throw new AdminException('audio_library_unavailable', 'The audio library schema is unavailable.', 503);
            }
            $libraryId = $this->integer($audioLibraryId, 1, PHP_INT_MAX, 'audioLibraryId');
            $builder->where(
                'media_assets.id IN (SELECT audio_tracks.media_asset_id FROM audio_tracks '
                . 'WHERE audio_tracks.library_id = ' . $libraryId
                . ' AND audio_tracks.media_asset_id IS NOT NULL AND audio_tracks.deleted_at IS NULL)',
                null,
                false
            );
        }
        $q = trim((string) ($filters['q'] ?? $filters['search'] ?? ''));
        if ($q !== '') {
            $builder->groupStart();
            if (ctype_digit($q)) {
                $builder->orWhere('media_assets.id', (int) $q);
            }
            $builder->orLike('media_assets.name', $q)->orLike('media_assets.original_filename', $q)
                ->orLike('CAST(media_assets.tags AS CHAR)', $q, 'both', false)->groupEnd();
        }
        $assignment = strtolower(trim((string) ($filters['assignment'] ?? '')));
        if ($assignment !== '') {
            if (!in_array($assignment, ['assigned', 'unassigned'], true)) {
                throw new AdminException('validation_failed', 'Assignment filter is invalid.', 422);
            }
            $ids = $this->relations->assignedIds();
            if ($assignment === 'assigned') {
                $ids ? $builder->whereIn('media_assets.id', $ids) : $builder->where('1 = 0', null, false);
            } elseif ($ids) {
                $builder->whereNotIn('media_assets.id', $ids);
            }
        }
    }

    private function facets(array $assigned): array
    {
        $facets = [];
        foreach (['category', 'resource_type', 'provider', 'format', 'visibility', 'status'] as $field) {
            $rows = $this->db->table('media_assets')->select($field . ' AS value, COUNT(*) AS count')
                ->where('deleted_at', null)->groupBy($field)->orderBy('count', 'DESC')->get()->getResultArray();
            $facets[$field === 'resource_type' ? 'resourceType' : $field] = array_map(static fn (array $row): array => [
                'value' => $row['value'], 'count' => (int) $row['count'],
            ], $rows);
        }
        $total = (int) $this->db->table('media_assets')->where('deleted_at', null)->countAllResults();
        $facets['assignment'] = [
            ['value' => 'assigned', 'count' => count($assigned)],
            ['value' => 'unassigned', 'count' => max(0, $total - count($assigned))],
        ];
        return $facets;
    }

    private function summary(array $asset, bool $assigned): array
    {
        $tags = $asset['tags'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }
        $url = null;
        if (($asset['status'] ?? '') === 'ready' && ($asset['visibility'] ?? '') === 'public') {
            try {
                $url = $this->media->presentTrusted($asset, ($asset['resource_type'] ?? '') === 'image' ? 'thumbnail' : null)['url'];
            } catch (MediaException $ignored) {
                $url = null;
            }
        }
        return [
            'id' => (int) $asset['id'], 'name' => (string) ($asset['name'] ?? ''),
            'filename' => $asset['original_filename'] ?? null, 'category' => $asset['category'],
            'resourceType' => $asset['resource_type'], 'mimeType' => $asset['mime_type'],
            'format' => $asset['format'] ?? null, 'fileSize' => isset($asset['file_size']) ? (int) $asset['file_size'] : null,
            'width' => isset($asset['width']) ? (int) $asset['width'] : null,
            'height' => isset($asset['height']) ? (int) $asset['height'] : null,
            'provider' => $asset['provider'], 'visibility' => $asset['visibility'],
            'status' => $asset['status'], 'revision' => (int) ($asset['revision'] ?? 1),
            'sourceUrl' => ($asset['provider'] ?? '') === 'external' ? ($asset['source_url'] ?? null) : null,
            'availabilityStatus' => ($asset['provider'] ?? '') === 'external'
                ? (string) ($asset['availability_status'] ?? 'unknown') : null,
            'availabilityCheckedAt' => ($asset['provider'] ?? '') === 'external'
                ? ($asset['availability_checked_at'] ?? null) : null,
            'owner' => !empty($asset['owner_user_id']) ? ['id' => (int) $asset['owner_user_id'], 'username' => $asset['owner_username'] ?? null] : null,
            'campaignId' => isset($asset['campaign_id']) ? (int) $asset['campaign_id'] : null,
            'tags' => is_array($tags) ? $tags : [],
            'assignment' => $assigned ? 'assigned' : 'unassigned', 'url' => $url,
            'createdAt' => $asset['created_at'] ?? null, 'updatedAt' => $asset['updated_at'] ?? null,
        ];
    }

    private function detailPayload(array $asset, array $presented, ?array $owner): array
    {
        $summary = $this->summary($asset + ['owner_username' => $owner['username'] ?? null], (bool) $this->relations->forAsset((int) $asset['id']));
        return array_merge($summary, [
            'description' => $asset['description'] ?? null,
            'customMetadata' => is_array($asset['custom_metadata'] ?? null) ? $asset['custom_metadata'] : [],
            'providerMetadata' => is_array($asset['metadata'] ?? null) ? $asset['metadata'] : [],
            'providerContainer' => $asset['provider_container'] ?? null,
            'providerAssetId' => $asset['provider_asset_id'] ?? null,
            'publicId' => $asset['public_id'] ?? null,
            'duration' => isset($asset['duration']) ? (float) $asset['duration'] : null,
            'owner' => $owner ? ['id' => (int) $owner['id'], 'username' => $owner['username'], 'email' => $owner['email']] : null,
            'url' => $presented['url'] ?? null,
            'variants' => $presented['variants'] ?? [],
            'relations' => $this->relations->forAsset((int) $asset['id']),
            'collections' => $this->assetCollections((int) $asset['id']),
            'audioTracks' => $this->audioTrackLinks((int) $asset['id']),
            'globalAudioLibraries' => $this->globalAudioLibraries(),
        ]);
    }

    private function globalAudioLibraries(): array
    {
        if (!$this->db->tableExists('audio_libraries') || !$this->db->tableExists('audio_tracks')) {
            return [];
        }
        $global = $this->db->table('audio_libraries libraries')
            ->select(
                'libraries.id, libraries.name, libraries.system_id, libraries.setting_id, libraries.is_active, '
                . 'systems.name AS system_name, settings.name AS setting_name, COUNT(tracks.id) AS track_count'
            )
            ->join('rpg_systems systems', 'systems.id = libraries.system_id', 'left')
            ->join('rpg_universes settings', 'settings.id = libraries.setting_id', 'left')
            ->join('audio_tracks tracks', 'tracks.library_id = libraries.id AND tracks.deleted_at IS NULL', 'left')
            ->where('libraries.scope', 'system')
            ->groupBy('libraries.id, libraries.name, libraries.system_id, libraries.setting_id, libraries.is_active, systems.name, settings.name')
            ->orderBy('settings.name', 'ASC')->orderBy('libraries.name', 'ASC')
            ->get()->getResultArray();
        return array_map([$this, 'presentAudioLibrary'], $global);
    }

    private function presentAudioLibrary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'owner' => !empty($row['owner_user_id']) ? [
                'id' => (int) $row['owner_user_id'],
                'username' => $row['owner_username'] ?? null,
            ] : null,
            'settingId' => !empty($row['setting_id']) ? (int) $row['setting_id'] : null,
            'settingName' => $row['setting_name'] ?? null,
            'systemId' => !empty($row['system_id']) ? (int) $row['system_id'] : null,
            'systemName' => $row['system_name'] ?? null,
            'isActive' => !array_key_exists('is_active', $row) || !empty($row['is_active']),
            'trackCount' => (int) ($row['track_count'] ?? 0),
            'assetCount' => (int) ($row['asset_count'] ?? $row['track_count'] ?? 0),
        ];
    }

    private function audioTrackLinks(int $assetId): array
    {
        return array_values(array_filter(
            $this->relations->forAsset($assetId),
            static fn (array $relation): bool => ($relation['module'] ?? null) === 'audio'
        ));
    }

    private function audioTrackRow(int $trackId): array
    {
        $row = $this->db->table('audio_tracks tracks')
            ->select('tracks.*, libraries.scope AS library_scope, libraries.name AS library_name')
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'inner')
            ->where('tracks.id', $trackId)->where('tracks.deleted_at', null)
            ->get()->getRowArray();
        if (!$row) {
            throw new AdminException('audio_track_not_found', 'The audio track was not found.', 404);
        }
        return $row;
    }

    private function audioTrackCopy(array $source, int $targetLibraryId): array
    {
        return [
            'library_id' => $targetLibraryId,
            'owner_user_id' => null,
            'title' => $source['title'],
            'category' => $source['category'],
            'source_type' => $source['source_type'],
            'provider' => $source['provider'],
            'provider_reference' => $source['provider_reference'],
            // A copied record shares the central asset. A legacy storage key is
            // intentionally never duplicated as a new write path.
            'storage_key' => null,
            'original_name' => $source['original_name'],
            'external_url' => $source['external_url'],
            'mime_type' => $source['mime_type'],
            'extension' => $source['extension'],
            'byte_size' => $source['byte_size'],
            'sha256' => $source['sha256'],
            'duration_seconds' => $source['duration_seconds'],
            'loop_enabled' => $source['loop_enabled'],
            'tags_json' => $source['tags_json'],
            'thumbnail_url' => $source['thumbnail_url'],
            'status' => $source['status'],
            'media_asset_id' => $source['media_asset_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function editableFields(array $input, bool $bulk): array
    {
        $update = [];
        if (array_key_exists('name', $input)) {
            $name = trim((string) $input['name']);
            if ($name === '' || mb_strlen($name) > 255) throw new AdminException('validation_failed', 'Asset name is invalid.', 422, ['name' => 'invalid']);
            $update['name'] = $name;
        }
        if (array_key_exists('description', $input)) {
            $description = trim((string) $input['description']);
            if (mb_strlen($description) > 10000) throw new AdminException('validation_failed', 'Asset description is too long.', 422);
            $update['description'] = $description !== '' ? $description : null;
        }
        if (array_key_exists('category', $input)) {
            $category = strtolower(trim((string) $input['category']));
            if (!in_array($category, MediaAssetModel::CATEGORIES, true)) throw new AdminException('validation_failed', 'Asset category is invalid.', 422);
            $update['category'] = $category;
        }
        if (array_key_exists('tags', $input)) $update['tags'] = $this->tags($input['tags']);
        if (array_key_exists('customMetadata', $input)) {
            if (!is_array($input['customMetadata']) || array_is_list($input['customMetadata'])) throw new AdminException('validation_failed', 'Custom metadata must be a JSON object.', 422);
            $encoded = json_encode($input['customMetadata']);
            if ($encoded === false || strlen($encoded) > 65535) throw new AdminException('validation_failed', 'Custom metadata is too large.', 422);
            $update['custom_metadata'] = $input['customMetadata'];
        }
        if (array_key_exists('visibility', $input)) {
            $visibility = strtolower(trim((string) $input['visibility']));
            if (!in_array($visibility, MediaAssetModel::VISIBILITIES, true)) throw new AdminException('validation_failed', 'Asset visibility is invalid.', 422);
            $update['visibility'] = $visibility;
            $campaignId = $input['campaignId'] ?? null;
            if ($visibility === 'campaign') {
                $update['campaign_id'] = $this->integer($campaignId, 1, PHP_INT_MAX, 'campaignId');
            } else {
                $update['campaign_id'] = null;
            }
        }
        return $update;
    }

    private function assertRelationInvariants(array $asset, string $visibility, $campaignId): void
    {
        foreach ($this->relations->forAsset((int) $asset['id']) as $relation) {
            if ($relation['module'] === 'characters' && ($visibility !== 'public' || ($asset['resource_type'] ?? '') !== 'image')) {
                throw new AdminException('asset_invariant_violation', 'Character set assets must remain public images.', 409);
            }
            if (($asset['provider'] ?? '') === 'external' && $relation['module'] === 'scenes') {
                // A single external URL may be used by scenes in multiple campaigns.
                // Its catalogue row remains public; scene membership controls use.
                continue;
            }
            if (in_array($relation['module'], ['scenes', 'map-creator'], true) && !empty($relation['campaignId'])
                && ($visibility !== 'campaign' || (int) $campaignId !== (int) $relation['campaignId'])) {
                throw new AdminException('asset_invariant_violation', 'Campaign map assets must remain in their campaign.', 409);
            }
        }
    }

    private function applyBulkTags(array $asset, array $payload): array
    {
        if (!array_key_exists('tags', $payload)) return $payload;
        $incoming = $this->tags($payload['tags']);
        $current = is_array($asset['tags'] ?? null) ? $asset['tags'] : [];
        $mode = strtolower((string) ($payload['tagMode'] ?? 'replace'));
        if ($mode === 'add') $payload['tags'] = array_values(array_unique(array_merge($current, $incoming)));
        elseif ($mode === 'remove') $payload['tags'] = array_values(array_diff($current, $incoming));
        elseif ($mode !== 'replace') throw new AdminException('validation_failed', 'Tag mode is invalid.', 422);
        return $payload;
    }

    private function tags($value): array
    {
        if (!is_array($value) || count($value) > 50) throw new AdminException('validation_failed', 'Tags must be an array of at most 50 values.', 422);
        $tags = [];
        foreach ($value as $tag) {
            $tag = mb_strtolower(trim((string) $tag));
            if ($tag === '' || mb_strlen($tag) > 64) throw new AdminException('validation_failed', 'A media tag is invalid.', 422);
            $tags[$tag] = true;
        }
        return array_keys($tags);
    }

    private function collectionFields(array $input, ?int $defaultOwner): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 150) throw new AdminException('validation_failed', 'Collection name is invalid.', 422);
        $owner = isset($input['ownerUserId']) ? $this->integer($input['ownerUserId'], 1, PHP_INT_MAX, 'ownerUserId') : $defaultOwner;
        $row = ['name' => $name, 'description' => trim((string) ($input['description'] ?? '')) ?: null];
        if ($owner !== null) $row['owner_user_id'] = $owner;
        return $row;
    }

    private function presentCollection(array $row): array
    {
        return ['id' => (int) $row['id'], 'name' => $row['name'], 'description' => $row['description'] ?? null,
            'owner' => ['id' => (int) $row['owner_user_id'], 'username' => $row['owner_username'] ?? null],
            'assetCount' => (int) ($row['asset_count'] ?? 0), 'createdAt' => $row['created_at'] ?? null];
    }

    private function assetCollections(int $assetId): array
    {
        $rows = $this->db->table('media_collections collection')->select('collection.*, users.username AS owner_username')
            ->join('media_collection_assets link', 'link.collection_id = collection.id')
            ->join('users', 'users.id = collection.owner_user_id', 'left')
            ->where('link.media_asset_id', $assetId)->orderBy('collection.name')->get()->getResultArray();
        return array_map([$this, 'presentCollection'], $rows);
    }

    private function asset(int $id): array
    {
        $asset = $id > 0 ? $this->assets->find($id) : null;
        if (!$asset) throw new AdminException('media_not_found', 'Media asset was not found.', 404);
        return $asset;
    }

    private function collection(int $id): array
    {
        $collection = $id > 0 ? $this->collections->find($id) : null;
        if (!$collection) throw new AdminException('media_collection_not_found', 'Media collection was not found.', 404);
        return $collection;
    }

    private function ids($values): array
    {
        if (!is_array($values)) return [];
        $ids = [];
        foreach ($values as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($id === false) throw new AdminException('validation_failed', 'An asset ID is invalid.', 422);
            $ids[(int) $id] = true;
        }
        return array_keys($ids);
    }

    private function integer($value, int $min, int $max, string $field): int
    {
        $result = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        if ($result === false) throw new AdminException('validation_failed', 'A numeric value is invalid.', 422, [$field => 'invalid']);
        return (int) $result;
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        $user = $userId > 0 && empty($auth['anonymous']) ? $this->db->table('users')->where('id', $userId)->where('deleted_at', null)->get()->getRowArray() : null;
        if (!$user) throw new AdminException('unauthorized', 'Authentication is required.', 401);
        if (strtolower((string) $user['role']) !== 'admin') throw new AdminException('forbidden', 'Administrator access is required.', 403);
        return $user;
    }

    private function assertSchema(): void
    {
        if (!$this->db->tableExists('media_collections') || !$this->db->fieldExists('revision', 'media_assets')) {
            throw new AdminException('media_schema_unavailable', 'The media library schema is unavailable.', 503);
        }
    }
}
