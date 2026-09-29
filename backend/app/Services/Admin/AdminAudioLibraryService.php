<?php

namespace App\Services\Admin;

use App\Models\AudioLibraryModel;
use App\Models\AudioTrackModel;
use App\Models\CampaignAudioTrackModel;
use App\Services\Audio\AudioTrackStorage;
use App\Services\Audio\ExternalAudioProviderRegistry;
use App\Services\Campaign\CampaignException;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

/** Administrator-owned audio catalog assigned to RPG systems and settings. */
final class AdminAudioLibraryService
{
    private $db;
    private $libraries;
    private $tracks;
    private $campaignTracks;
    private $storage;
    private $providers;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?AudioTrackStorage $storage = null,
        ?ExternalAudioProviderRegistry $providers = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->libraries = new AudioLibraryModel($this->db);
        $this->tracks = new AudioTrackModel($this->db);
        $this->campaignTracks = new CampaignAudioTrackModel($this->db);
        $this->storage = $storage ?: new AudioTrackStorage();
        $this->providers = $providers ?: new ExternalAudioProviderRegistry();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function overview(array $auth): array
    {
        $this->verifiedAdmin($auth);
        $libraries = $this->libraryRows();
        $tracks = $this->trackRows();
        $tracksByLibrary = [];
        foreach ($tracks as $track) {
            $tracksByLibrary[(int) $track['library_id']][] = $this->presentTrack($track);
        }
        $systemIdsBySetting = [];
        foreach ($this->db->table('rpg_system_universes')
            ->select('system_id,universe_id')->where('is_active', 1)
            ->get()->getResultArray() as $pair) {
            $systemIdsBySetting[(int) $pair['universe_id']][] = (int) $pair['system_id'];
        }
        return [
            'libraries' => array_map(function (array $library) use ($tracksByLibrary): array {
                $presented = $this->presentLibrary($library);
                $presented['tracks'] = $tracksByLibrary[(int) $library['id']] ?? [];
                $presented['trackCount'] = count($presented['tracks']);
                return $presented;
            }, $libraries),
            'systems' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
            ], $this->db->table('rpg_systems')->select('id,code,name')
                ->orderBy('name', 'ASC')->get()->getResultArray()),
            'settings' => array_map(static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'name' => (string) $row['name'],
                'defaultSystemId' => !empty($row['default_system_id'])
                    ? (int) $row['default_system_id'] : null,
                'systemIds' => $systemIdsBySetting[(int) $row['id']] ?? [],
            ], $this->db->table('rpg_universes')
                ->select('id,code,name,default_system_id')
                ->orderBy('name', 'ASC')->get()->getResultArray()),
            'upload' => [
                'maxBytes' => $this->storage->limitBytes(),
                'extensions' => ['mp3', 'wav', 'ogg', 'oga', 'webm', 'm4a', 'mp4', 'aac', 'flac'],
            ],
        ];
    }

    public function createLibrary(array $auth, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $data = $this->libraryPayload($payload);
        if (!$this->libraries->insert($data + ['scope' => 'system', 'owner_user_id' => null])) {
            throw new AdminException('audio_library_write_failed', 'Audio library could not be created.', 500);
        }
        return ['library' => $this->presentLibrary($this->libraryRow(
            (int) $this->libraries->getInsertID()
        ))];
    }

    public function updateLibrary(array $auth, int $libraryId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $this->libraryRow($libraryId);
        $data = $this->libraryPayload($payload);
        if (!$this->libraries->update($libraryId, $data)) {
            throw new AdminException('audio_library_write_failed', 'Audio library could not be updated.', 500);
        }
        return ['library' => $this->presentLibrary($this->libraryRow($libraryId))];
    }

    public function deleteLibrary(array $auth, int $libraryId): array
    {
        $this->verifiedAdmin($auth);
        $this->libraryRow($libraryId);
        $rows = $this->db->table('audio_tracks')->select('id,storage_key')
            ->where('library_id', $libraryId)->where('deleted_at', null)
            ->get()->getResultArray();
        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                $this->campaignTracks->where('audio_track_id', (int) $row['id'])->delete();
                $this->tracks->delete((int) $row['id'], true);
            }
            if (!$this->libraries->delete($libraryId) || $this->db->transStatus() === false) {
                throw new \RuntimeException('Library deletion failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new AdminException('audio_library_write_failed', 'Audio library could not be deleted.', 500);
        }
        foreach ($rows as $row) {
            if (!empty($row['storage_key'])) {
                $this->storage->remove((string) $row['storage_key']);
            }
        }
        return ['deleted' => true, 'libraryId' => $libraryId];
    }

    public function upload(
        array $auth,
        int $libraryId,
        ?UploadedFile $file,
        array $payload
    ): array {
        $this->verifiedAdmin($auth);
        $this->libraryRow($libraryId);
        $metadata = $this->trackPayload(
            $payload,
            $file ? pathinfo((string) $file->getClientName(), PATHINFO_FILENAME) : ''
        );
        try {
            $stored = $this->storage->storeForLibrary($libraryId, $file);
        } catch (CampaignException $exception) {
            throw new AdminException(
                $exception->errorCode(), $exception->getMessage(),
                $exception->status(), $exception->details()
            );
        }
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            try {
                $central = $this->media->uploadFile($auth, $stored['path'], [
                    'filename' => $stored['original_name'], 'mimeType' => $stored['mime_type'],
                    'category' => 'audio', 'visibility' => 'private',
                ]);
            } catch (MediaException $exception) {
                $this->storage->remove($stored['storage_key']);
                throw new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
        }
        $track = $metadata + $stored + [
            'library_id' => $libraryId,
            'owner_user_id' => null,
            'source_type' => 'upload',
            'status' => 'ready',
        ];
        unset($track['path']);
        if ($central) {
            $track['storage_key'] = null;
            $track['media_asset_id'] = (int) $central['id'];
        }
        if (!$this->tracks->insert($track)) {
            $this->storage->remove((string) $stored['storage_key']);
            throw new AdminException('audio_track_write_failed', 'Audio track could not be saved.', 500);
        }
        if ($central) $this->storage->remove((string) $stored['storage_key']);
        return ['track' => $this->presentTrack($this->trackRow(
            (int) $this->tracks->getInsertID()
        ))];
    }

    public function addExternal(
        array $auth,
        int $libraryId,
        array $payload
    ): array {
        $this->verifiedAdmin($auth);
        $this->libraryRow($libraryId);
        try {
            $provider = $this->providers->resolve(trim((string) ($payload['url'] ?? '')));
        } catch (CampaignException $exception) {
            throw new AdminException(
                $exception->errorCode(), $exception->getMessage(),
                $exception->status(), $exception->details()
            );
        }
        $metadata = $this->trackPayload($payload, (string) $provider['provider']);
        if (empty($metadata['thumbnail_url'])) {
            $metadata['thumbnail_url'] = $provider['thumbnailUrl'];
        }
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            try {
                $central = $this->media->registerExternalUrl($auth, [
                    'sourceUrl' => $provider['url'],
                    'mediaType' => 'audio',
                    'category' => 'audio',
                    'name' => $metadata['title'],
                    'tags' => $metadata['tags_json'],
                ]);
            } catch (MediaException $exception) {
                throw new AdminException(
                    $exception->errorCode(), $exception->getMessage(),
                    $exception->status(), $exception->errors()
                );
            }
        }
        $track = $metadata + [
            'library_id' => $libraryId,
            'owner_user_id' => null,
            'source_type' => 'external',
            'provider' => $provider['provider'],
            'provider_reference' => $provider['reference'],
            'external_url' => $provider['url'],
            'status' => 'ready',
        ];
        if ($central) {
            $track['media_asset_id'] = (int) $central['id'];
        }
        if (!$this->tracks->insert($track)) {
            throw new AdminException('audio_track_write_failed', 'Audio track could not be saved.', 500);
        }
        return ['track' => $this->presentTrack($this->trackRow(
            (int) $this->tracks->getInsertID()
        ))];
    }

    public function updateTrack(array $auth, int $trackId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $existing = $this->trackRow($trackId);
        $payload += [
            'title' => $existing['title'],
            'category' => $existing['category'],
            'duration' => $existing['duration_seconds'],
            'loop' => !empty($existing['loop_enabled']),
            'tags' => $existing['tags_json'] ?? [],
            'thumbnail' => $existing['thumbnail_url'] ?? null,
        ];
        $data = $this->trackPayload($payload, (string) $existing['title']);
        if (!$this->tracks->update($trackId, $data)) {
            throw new AdminException('audio_track_write_failed', 'Audio track could not be updated.', 500);
        }
        return ['track' => $this->presentTrack($this->trackRow($trackId))];
    }

    public function deleteTrack(array $auth, int $trackId): array
    {
        $this->verifiedAdmin($auth);
        $row = $this->trackRow($trackId);
        $this->db->transBegin();
        try {
            $this->campaignTracks->where('audio_track_id', $trackId)->delete();
            if (!$this->tracks->delete($trackId, true) || $this->db->transStatus() === false) {
                throw new \RuntimeException('Track deletion failed.');
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw new AdminException('audio_track_write_failed', 'Audio track could not be deleted.', 500);
        }
        if (!empty($row['storage_key'])) {
            $this->storage->remove((string) $row['storage_key']);
        }
        return ['deleted' => true, 'trackId' => $trackId];
    }

    private function libraryPayload(array $payload): array
    {
        $name = trim((string) ($payload['name'] ?? ''));
        $systemId = filter_var($payload['systemId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $settingId = filter_var($payload['settingId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if (mb_strlen($name) < 2 || mb_strlen($name) > 180
            || $systemId === false || $settingId === false) {
            throw new AdminException('validation_failed', 'Audio library data is invalid.', 422);
        }
        $available = $this->db->table('rpg_system_universes')
            ->where('system_id', (int) $systemId)
            ->where('universe_id', (int) $settingId)
            ->where('is_active', 1)->countAllResults() > 0;
        if (!$available) {
            throw new AdminException(
                'validation_failed',
                'Selected RPG system is not available for this setting.',
                422
            );
        }
        return [
            'name' => $name,
            'system_id' => (int) $systemId,
            'setting_id' => (int) $settingId,
            'is_active' => filter_var(
                $payload['isActive'] ?? true, FILTER_VALIDATE_BOOLEAN
            ) ? 1 : 0,
        ];
    }

    private function trackPayload(array $payload, string $fallbackTitle): array
    {
        $title = trim((string) ($payload['title'] ?? $fallbackTitle));
        $category = strtolower(trim((string) ($payload['category'] ?? 'music')));
        if ($title === '' || mb_strlen($title) > 180
            || !in_array($category, ['music', 'ambient', 'sfx'], true)) {
            throw new AdminException('validation_failed', 'Audio metadata is invalid.', 422);
        }
        $duration = $payload['duration'] ?? $payload['durationSeconds'] ?? null;
        if ($duration !== null && (!is_numeric($duration)
            || (float) $duration < 0 || (float) $duration > 86400)) {
            throw new AdminException('validation_failed', 'Audio duration is invalid.', 422);
        }
        $tags = $payload['tags'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded)
                ? $decoded
                : preg_split('/\s*,\s*/', $tags, -1, PREG_SPLIT_NO_EMPTY);
        }
        if (!is_array($tags) || count($tags) > 20) {
            throw new AdminException('validation_failed', 'Audio tags are invalid.', 422);
        }
        $tags = array_values(array_unique(array_filter(array_map(
            static fn ($tag): string => mb_substr(trim((string) $tag), 0, 48),
            $tags
        ))));
        $thumbnail = trim((string) ($payload['thumbnail'] ?? $payload['thumbnailUrl'] ?? ''));
        if (strlen($thumbnail) > 2048
            || ($thumbnail !== '' && !preg_match('#^(https://|/)#i', $thumbnail))) {
            throw new AdminException('validation_failed', 'Thumbnail URL is invalid.', 422);
        }
        return [
            'title' => $title,
            'category' => $category,
            'duration_seconds' => $duration === null ? null : round((float) $duration, 3),
            'loop_enabled' => filter_var(
                $payload['loop'] ?? false, FILTER_VALIDATE_BOOLEAN
            ) ? 1 : 0,
            'tags_json' => $tags,
            'thumbnail_url' => $thumbnail ?: null,
        ];
    }

    private function libraryRows(): array
    {
        return $this->db->table('audio_libraries libraries')
            ->select('libraries.*,systems.name AS system_name,settings.name AS setting_name')
            ->join('rpg_systems systems', 'systems.id=libraries.system_id', 'left')
            ->join('rpg_universes settings', 'settings.id=libraries.setting_id', 'left')
            ->where('libraries.scope', 'system')
            ->orderBy('settings.name', 'ASC')->orderBy('libraries.name', 'ASC')
            ->get()->getResultArray();
    }

    private function libraryRow(int $libraryId): array
    {
        $row = $this->db->table('audio_libraries libraries')
            ->select('libraries.*,systems.name AS system_name,settings.name AS setting_name')
            ->join('rpg_systems systems', 'systems.id=libraries.system_id', 'left')
            ->join('rpg_universes settings', 'settings.id=libraries.setting_id', 'left')
            ->where('libraries.id', $libraryId)->where('libraries.scope', 'system')
            ->get()->getRowArray();
        if (!$row) {
            throw new AdminException('audio_library_not_found', 'Audio library was not found.', 404);
        }
        return $row;
    }

    private function trackRows(): array
    {
        return $this->db->table('audio_tracks tracks')
            ->select('tracks.*,libraries.name AS library_name')
            ->join('audio_libraries libraries', 'libraries.id=tracks.library_id', 'inner')
            ->where('libraries.scope', 'system')->where('tracks.deleted_at', null)
            ->orderBy('tracks.category', 'ASC')->orderBy('tracks.title', 'ASC')
            ->get()->getResultArray();
    }

    private function trackRow(int $trackId): array
    {
        $row = $this->db->table('audio_tracks tracks')
            ->select('tracks.*,libraries.name AS library_name,libraries.scope AS library_scope')
            ->join('audio_libraries libraries', 'libraries.id=tracks.library_id', 'inner')
            ->where('tracks.id', $trackId)->where('tracks.deleted_at', null)
            ->where('libraries.scope', 'system')->get()->getRowArray();
        if (!$row) {
            throw new AdminException('audio_track_not_found', 'Audio track was not found.', 404);
        }
        return $row;
    }

    private function presentLibrary(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'systemId' => !empty($row['system_id']) ? (int) $row['system_id'] : null,
            'systemName' => $row['system_name'] ?? null,
            'settingId' => !empty($row['setting_id']) ? (int) $row['setting_id'] : null,
            'settingName' => $row['setting_name'] ?? null,
            'isActive' => !empty($row['is_active']),
        ];
    }

    private function presentTrack(array $row): array
    {
        $tags = $row['tags_json'] ?? [];
        if (is_string($tags)) {
            $decoded = json_decode($tags, true);
            $tags = is_array($decoded) ? $decoded : [];
        }
        return [
            'id' => (int) $row['id'],
            'libraryId' => (int) $row['library_id'],
            'title' => (string) $row['title'],
            'category' => (string) $row['category'],
            'sourceType' => (string) $row['source_type'],
            'provider' => $row['provider'] ?? null,
            'duration' => $row['duration_seconds'] === null
                ? null : (float) $row['duration_seconds'],
            'loop' => !empty($row['loop_enabled']),
            'tags' => is_array($tags) ? $tags : [],
            'thumbnail' => $row['thumbnail_url'] ?? null,
            'originalName' => $row['original_name'] ?? null,
            'mimeType' => $row['mime_type'] ?? null,
            'fileSize' => !empty($row['byte_size']) ? (int) $row['byte_size'] : null,
        ];
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->db->table('users')->where('id', $userId)
            ->where('deleted_at', null)->get()->getRowArray();
        if (!$user) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        if (strtolower((string) $user['role']) !== 'admin') {
            throw new AdminException('forbidden', 'Administrator access is required.', 403);
        }
        return $user;
    }
}
