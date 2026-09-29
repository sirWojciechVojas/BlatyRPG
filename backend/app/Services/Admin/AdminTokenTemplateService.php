<?php

namespace App\Services\Admin;

use App\Models\TokenTemplateAssetModel;
use App\Models\TokenTemplateModel;
use App\Services\Token\TokenException;
use App\Services\Token\TokenTemplateAssetStorage;
use App\Services\Token\TokenTemplatePayloadValidator;
use App\Services\Token\TokenTemplatePresenter;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

final class AdminTokenTemplateService
{
    private $db;
    private $templates;
    private $assets;
    private $validator;
    private $storage;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?TokenTemplateModel $templates = null,
        ?TokenTemplateAssetModel $assets = null,
        ?TokenTemplatePayloadValidator $validator = null,
        ?TokenTemplateAssetStorage $storage = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->templates = $templates ?: new TokenTemplateModel($this->db);
        $this->assets = $assets ?: new TokenTemplateAssetModel($this->db);
        $this->validator = $validator ?: new TokenTemplatePayloadValidator();
        $this->storage = $storage ?: new TokenTemplateAssetStorage();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(array $auth): array
    {
        $this->verifiedAdmin($auth);
        $rows = $this->templates->orderBy('name', 'ASC')->orderBy('id', 'ASC')->findAll();
        return ['items' => array_map(fn (array $row): array => $this->present($row), $rows)];
    }

    public function create(array $auth, array $payload, ?UploadedFile $file = null): array
    {
        $admin = $this->verifiedAdmin($auth);
        $hasUpload = $this->hasUpload($file);
        $validated = $this->validator->create($payload, $hasUpload);
        $this->assertValid($validated);
        $stored = null;
        $this->db->transBegin();
        try {
            $data = $validated['data'];
            if ($hasUpload) {
                $stored = $this->storeAsset($auth, $file, (int) $admin['id']);
                $data['image_asset_id'] = $stored['id'];
                $data['image_url'] = null;
            }
            $data += [
                'revision' => 1,
                'created_by_user_id' => (int) $admin['id'],
                'updated_by_user_id' => (int) $admin['id'],
            ];
            if (!$this->templates->insert($data)) {
                throw new AdminException('validation_failed', 'Token template could not be created.', 422, $this->templates->errors());
            }
            $id = (int) $this->templates->getInsertID();
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($stored && !empty($stored['storage_key'])) $this->storage->discard($stored['storage_key']);
            if ($exception instanceof AdminException) throw $exception;
            throw $this->convert($exception);
        }
        return ['template' => $this->present($this->find($id))];
    }

    public function update(
        array $auth,
        int $templateId,
        array $payload,
        ?UploadedFile $file = null
    ): array {
        $admin = $this->verifiedAdmin($auth);
        $current = $this->find($templateId);
        $hasUpload = $this->hasUpload($file);
        $validated = $this->validator->update($payload, $hasUpload);
        $this->assertValid($validated);
        $data = $validated['data'];
        $stored = null;
        $this->db->transBegin();
        try {
            if ($hasUpload) {
                $stored = $this->storeAsset($auth, $file, (int) $admin['id']);
                $data['image_asset_id'] = $stored['id'];
                $data['image_url'] = null;
            } elseif (array_key_exists('image_url', $data)) {
                $data['image_asset_id'] = null;
            }
            $nextImageUrl = array_key_exists('image_url', $data)
                ? $data['image_url'] : ($current['image_url'] ?? null);
            $nextAssetId = array_key_exists('image_asset_id', $data)
                ? $data['image_asset_id'] : ($current['image_asset_id'] ?? null);
            if (!$nextImageUrl && !$nextAssetId) {
                throw new AdminException('validation_failed', 'A token template image is required.', 422, [
                    'image' => 'An image URL or uploaded image is required.',
                ]);
            }
            $data['updated_by_user_id'] = (int) $admin['id'];
            $this->writeRevision($templateId, $validated['revision'], $data);
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($stored && !empty($stored['storage_key'])) $this->storage->discard($stored['storage_key']);
            if ($exception instanceof AdminException) throw $exception;
            throw $this->convert($exception);
        }
        return ['template' => $this->present($this->find($templateId))];
    }

    public function delete(array $auth, int $templateId, array $payload): array
    {
        $admin = $this->verifiedAdmin($auth);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($templateId);
        $this->writeRevision($templateId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_by_user_id' => (int) $admin['id'],
        ]);
        return ['deleted' => true, 'id' => $templateId];
    }

    public function asset(array $auth, int $assetId): array
    {
        $this->verifiedAdmin($auth);
        $asset = $this->assets->find($assetId);
        if (!$asset) throw new AdminException('token_template_asset_not_found', 'Token image was not found.', 404);
        if (!empty($asset['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $asset['media_asset_id']);
            } catch (MediaException $exception) {
                throw new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['asset' => $asset, 'url' => $media['url']];
        }
        try {
            $path = $this->storage->path((string) $asset['storage_key']);
        } catch (TokenException $exception) {
            throw new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->details());
        }
        return ['asset' => $asset, 'path' => $path];
    }

    private function storeAsset(array $auth, UploadedFile $file, int $userId): array
    {
        try {
            $data = $this->storage->store($file) + [
                'created_by_user_id' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ];
        } catch (TokenException $exception) {
            throw $this->convert($exception);
        }
        $legacyKey = $data['storage_key'];
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'token_template_assets')) {
            try {
                $central = $this->media->uploadFile($auth, $this->storage->path($data['storage_key']), [
                    'filename' => $data['original_name'], 'mimeType' => $data['mime_type'],
                    'category' => 'characters', 'visibility' => 'private', 'ownerUserId' => $userId,
                ]);
                $data['storage_key'] = null;
                $data['media_asset_id'] = (int) $central['id'];
            } catch (MediaException $exception) {
                $this->storage->discard($data['storage_key']);
                throw new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
        }
        if (!$this->assets->insert($data)) {
            $this->storage->discard($legacyKey);
            throw new AdminException('token_template_asset_storage_failed', 'Token image metadata could not be stored.', 500);
        }
        if (empty($data['storage_key'])) $this->storage->discard($legacyKey);
        return $data + ['id' => (int) $this->assets->getInsertID()];
    }

    private function present(array $row): array
    {
        $assetId = isset($row['image_asset_id']) ? (int) $row['image_asset_id'] : null;
        $url = $assetId
            ? '/api/admin/token-template-assets/' . $assetId . '/file'
            : (string) ($row['image_url'] ?? '');
        $result = TokenTemplatePresenter::present($row, $url);
        if ($assetId) {
            $asset = $this->assets->find($assetId);
            if ($asset) {
                $result['imageAsset'] = [
                    'id' => $assetId,
                    'name' => (string) $asset['original_name'],
                    'mimeType' => (string) $asset['mime_type'],
                    'byteSize' => (int) $asset['byte_size'],
                    'width' => (int) $asset['width'],
                    'height' => (int) $asset['height'],
                ];
            }
        }
        return $result;
    }

    private function find(int $templateId): array
    {
        $row = $templateId > 0 ? $this->templates->find($templateId) : null;
        if (!$row) throw new AdminException('token_template_not_found', 'Token template was not found.', 404);
        return $row;
    }

    private function writeRevision(int $templateId, int $revision, array $data): void
    {
        foreach (['bars_json', 'vision_json'] as $field) {
            if (isset($data[$field]) && is_array($data[$field])) {
                $data[$field] = json_encode($data[$field], JSON_UNESCAPED_UNICODE);
            }
        }
        $this->db->table('token_templates')->set($data)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)
            ->where('id', $templateId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $current = $this->templates->find($templateId);
        if (!$current) throw new AdminException('token_template_not_found', 'Token template was not found.', 404);
        throw new AdminException('revision_conflict', 'Token template changed since it was loaded.', 409, [
            'currentRevision' => (int) $current['revision'],
        ]);
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new AdminException('unauthorized', 'Authentication is required.', 401);
        }
        $user = $this->db->table('users')->where('id', $userId)
            ->where('deleted_at', null)->get()->getRowArray();
        if (!$user) throw new AdminException('unauthorized', 'Authentication is required.', 401);
        if (strtolower((string) $user['role']) !== 'admin') {
            throw new AdminException('forbidden', 'Administrator access is required.', 403);
        }
        return $user;
    }

    private function hasUpload(?UploadedFile $file): bool
    {
        return $file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE;
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new AdminException('validation_failed', 'Token template payload is invalid.', 422, $validated['errors']);
        }
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new AdminException('token_template_write_failed', 'Token template changes could not be saved.', 500);
        }
        $this->db->transCommit();
    }

    private function convert(\Throwable $exception): AdminException
    {
        if ($exception instanceof TokenException) {
            return new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->details());
        }
        return new AdminException('token_template_write_failed', 'Token template changes could not be saved.', 500);
    }
}
