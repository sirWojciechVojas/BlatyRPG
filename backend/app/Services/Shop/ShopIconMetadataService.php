<?php

namespace App\Services\Shop;

use App\Models\ShopIconMetadataModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class ShopIconMetadataService
{
    private const SMALL_ICON_SIZE = 42;
    private const LARGE_ICON_SIZE = 144;
    private const MAX_FILE_SIZE = 4194304;

    private $model;

    public function __construct(?ShopIconMetadataModel $model = null)
    {
        $this->model = $model ?? new ShopIconMetadataModel();
    }

    public function listForCampaign(int $campaignId): array
    {
        return array_map([$this, 'toPayload'], $this->model
            ->where('campaign_id', $campaignId)
            ->orderBy('icon_class', 'ASC')
            ->findAll());
    }

    public function save(int $campaignId, string $iconClass, array $input): ?array
    {
        $iconClass = strtolower(trim($iconClass));
        if (!preg_match('/^v\d{4}$/', $iconClass)) {
            return null;
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 160) {
            return null;
        }
        $existing = $this->model->where(['campaign_id' => $campaignId, 'icon_class' => $iconClass])->first();
        $record = [
            'campaign_id' => $campaignId,
            'icon_class' => $iconClass,
            'name' => $name,
            'source_name' => mb_substr(trim((string) ($input['sourceName'] ?? '')), 0, 255),
            'description' => mb_substr(trim((string) ($input['description'] ?? '')), 0, 2000),
            'special_marks' => mb_substr(trim((string) ($input['specialMarks'] ?? '')), 0, 1000),
            'type_keys_json' => $this->codes($input['typeKeys'] ?? [], 20),
            'subtype_keys_json' => $this->codes($input['subtypeKeys'] ?? [], 30),
            'item_classes_json' => $this->codes($input['itemClasses'] ?? [], 30),
            'item_genres_json' => $this->codes($input['itemGenres'] ?? [], 50),
        ];
        if ($existing) {
            $this->model->update((int) $existing['id'], $record);
            $id = (int) $existing['id'];
        } else {
            $id = (int) $this->model->insert($record, true);
        }
        $saved = $this->model->find($id);
        return $saved ? $this->toPayload($saved) : null;
    }

    public function createFromUpload(int $campaignId, ?UploadedFile $file): ?array
    {
        if (!$file || !$file->isValid() || $file->hasMoved() || $file->getSize() > 4 * 1024 * 1024) {
            return null;
        }
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $mime = strtolower((string) $file->getMimeType());
        if (!isset($extensions[$mime])) {
            return null;
        }
        $dimensions = @getimagesize($file->getTempName());
        if (
            !$dimensions ||
            (int) ($dimensions[0] ?? 0) < 16 ||
            (int) ($dimensions[1] ?? 0) < 16 ||
            (int) ($dimensions[0] ?? 0) > 2048 ||
            (int) ($dimensions[1] ?? 0) > 2048
        ) {
            return null;
        }
        $iconClass = $this->nextIconClass($campaignId);
        if ($iconClass === null) {
            return null;
        }
        $relativeDirectory = 'uploads/shop-icons/' . $campaignId;
        $absoluteDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            return null;
        }
        $filename = $iconClass . '.' . $extensions[$mime];
        $originalName = mb_substr((string) $file->getClientName(), 0, 255);
        $file->move($absoluteDirectory, $filename, true);
        if (!$file->hasMoved()) {
            return null;
        }
        $name = trim((string) pathinfo($originalName, PATHINFO_FILENAME)) ?: $iconClass;
        $saved = $this->save($campaignId, $iconClass, [
            'name' => mb_substr($name, 0, 160),
            'sourceName' => $originalName,
            'description' => '',
            'specialMarks' => '',
            'typeKeys' => ['MISC'],
            'subtypeKeys' => ['OTHER'],
        ]);
        if (!$saved) {
            @unlink($absoluteDirectory . DIRECTORY_SEPARATOR . $filename);
            return null;
        }
        $record = $this->model->where([
            'campaign_id' => $campaignId,
            'icon_class' => $iconClass,
        ])->first();
        if (!$record) {
            return null;
        }
        $this->model->update((int) $record['id'], [
            'image_path' => '/' . $relativeDirectory . '/' . $filename,
        ]);
        $updated = $this->model->find((int) $record['id']);
        return $updated ? $this->toPayload($updated) : null;
    }

    public function createFromUploads(
        int $campaignId,
        ?UploadedFile $smallFile,
        ?UploadedFile $largeFile
    ): ?array {
        if (
            !$this->validImage($smallFile, self::SMALL_ICON_SIZE) ||
            !$this->validImage($largeFile, self::LARGE_ICON_SIZE)
        ) {
            return null;
        }
        $iconClass = $this->nextIconClass($campaignId);
        if ($iconClass === null) {
            return null;
        }
        $originalName = mb_substr((string) $largeFile->getClientName(), 0, 255);
        $name = trim((string) pathinfo($originalName, PATHINFO_FILENAME)) ?: $iconClass;
        $saved = $this->save($campaignId, $iconClass, [
            'name' => mb_substr($name, 0, 160),
            'sourceName' => $originalName,
            'description' => '',
            'specialMarks' => '',
            'typeKeys' => ['MISC'],
            'subtypeKeys' => ['OTHER'],
        ]);
        if (!$saved) {
            return null;
        }
        $updated = $this->replaceImages($campaignId, $iconClass, $smallFile, $largeFile);
        if (!$updated) {
            $this->delete($campaignId, $iconClass);
        }
        return $updated;
    }

    public function replaceImages(
        int $campaignId,
        string $iconClass,
        ?UploadedFile $smallFile,
        ?UploadedFile $largeFile,
        array $metadata = []
    ): ?array {
        $iconClass = strtolower(trim($iconClass));
        if (
            !preg_match('/^v\d{4}$/', $iconClass) ||
            !$this->validImage($smallFile, self::SMALL_ICON_SIZE) ||
            !$this->validImage($largeFile, self::LARGE_ICON_SIZE)
        ) {
            return null;
        }
        $record = $this->model->where([
            'campaign_id' => $campaignId,
            'icon_class' => $iconClass,
        ])->first();
        if (!$record) {
            $saved = $this->save($campaignId, $iconClass, $metadata);
            if (!$saved) {
                return null;
            }
            $record = $this->model->where([
                'campaign_id' => $campaignId,
                'icon_class' => $iconClass,
            ])->first();
        }
        if (!$record) {
            return null;
        }

        $relativeDirectory = 'uploads/shop-icons/' . $campaignId;
        $absoluteDirectory = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relativeDirectory;
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0775, true) && !is_dir($absoluteDirectory)) {
            return null;
        }
        try {
            $revision = bin2hex(random_bytes(6));
        } catch (\Throwable $exception) {
            $revision = str_replace('.', '', uniqid('', true));
        }
        $smallName = $iconClass . '-42-' . $revision . '.' . $this->imageExtension($smallFile);
        $largeName = $iconClass . '-144-' . $revision . '.' . $this->imageExtension($largeFile);
        $smallFile->move($absoluteDirectory, $smallName, false);
        if (!$smallFile->hasMoved()) {
            return null;
        }
        $largeFile->move($absoluteDirectory, $largeName, false);
        if (!$largeFile->hasMoved()) {
            @unlink($absoluteDirectory . DIRECTORY_SEPARATOR . $smallName);
            return null;
        }
        $smallPath = '/' . $relativeDirectory . '/' . $smallName;
        $largePath = '/' . $relativeDirectory . '/' . $largeName;
        $oldPaths = $this->imagePaths($record);
        if (!$this->model->update((int) $record['id'], [
            // image_path remains populated for older clients.
            'image_path' => $largePath,
            'image_path_small' => $smallPath,
            'image_path_large' => $largePath,
        ])) {
            @unlink($absoluteDirectory . DIRECTORY_SEPARATOR . $smallName);
            @unlink($absoluteDirectory . DIRECTORY_SEPARATOR . $largeName);
            return null;
        }
        $this->deleteImagePaths($oldPaths);
        $updated = $this->model->find((int) $record['id']);
        return $updated ? $this->toPayload($updated) : null;
    }

    public function delete(int $campaignId, string $iconClass): bool
    {
        $record = $this->model->where([
            'campaign_id' => $campaignId,
            'icon_class' => strtolower(trim($iconClass)),
        ])->first();
        if (!$record) {
            return true;
        }
        $deleted = (bool) $this->model->delete((int) $record['id']);
        if ($deleted) {
            $this->deleteImagePaths($this->imagePaths($record));
        }
        return $deleted;
    }

    private function validImage(?UploadedFile $file, int $expectedSize): bool
    {
        if (!$file || !$file->isValid() || $file->hasMoved() || $file->getSize() > self::MAX_FILE_SIZE) {
            return false;
        }
        if ($this->imageExtension($file) === null) {
            return false;
        }
        $dimensions = @getimagesize($file->getTempName());
        return $dimensions &&
            (int) ($dimensions[0] ?? 0) === $expectedSize &&
            (int) ($dimensions[1] ?? 0) === $expectedSize;
    }

    private function imageExtension(?UploadedFile $file): ?string
    {
        if (!$file) {
            return null;
        }
        $extensions = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        return $extensions[strtolower((string) $file->getMimeType())] ?? null;
    }

    private function imagePaths(array $record): array
    {
        return array_values(array_unique(array_filter([
            (string) ($record['image_path'] ?? ''),
            (string) ($record['image_path_small'] ?? ''),
            (string) ($record['image_path_large'] ?? ''),
        ])));
    }

    private function deleteImagePaths(array $paths): void
    {
        foreach ($paths as $path) {
            $imagePath = ltrim((string) $path, '/');
            if ($imagePath === '' || strpos($imagePath, 'uploads/shop-icons/') !== 0) {
                continue;
            }
            $absolutePath = rtrim(FCPATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $imagePath;
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
        }
    }

    private function nextIconClass(int $campaignId): ?string
    {
        $maximum = 1375;
        foreach ($this->model->select('icon_class')->where('campaign_id', $campaignId)->findAll() as $row) {
            if (preg_match('/^v(\d{4})$/', (string) ($row['icon_class'] ?? ''), $matches)) {
                $maximum = max($maximum, (int) $matches[1]);
            }
        }
        return $maximum < 9999
            ? 'v' . str_pad((string) ($maximum + 1), 4, '0', STR_PAD_LEFT)
            : null;
    }

    private function codes($values, int $limit): array
    {
        $values = is_array($values) ? $values : preg_split('/[,;\n]+/', (string) $values);
        $result = [];
        foreach ($values as $value) {
            $code = strtoupper(trim((string) $value));
            if ($code !== '' && preg_match('/^[A-Z0-9_-]{1,64}$/', $code)) {
                $result[$code] = true;
            }
        }
        return array_slice(array_keys($result), 0, $limit);
    }

    private function toPayload(array $row): array
    {
        return [
            'iconClass' => (string) $row['icon_class'],
            'name' => (string) $row['name'],
            'sourceName' => (string) ($row['source_name'] ?? ''),
            'imageUrl' => (string) ($row['image_path'] ?? ''),
            'imageUrlSmall' => (string) ($row['image_path_small'] ?? $row['image_path'] ?? ''),
            'imageUrlLarge' => (string) ($row['image_path_large'] ?? $row['image_path'] ?? ''),
            'description' => (string) ($row['description'] ?? ''),
            'specialMarks' => (string) ($row['special_marks'] ?? ''),
            'typeKeys' => (array) ($row['type_keys_json'] ?? []),
            'subtypeKeys' => (array) ($row['subtype_keys_json'] ?? []),
            'itemClasses' => (array) ($row['item_classes_json'] ?? []),
            'itemGenres' => (array) ($row['item_genres_json'] ?? []),
            'updatedAt' => $row['updated_at'] ?? null,
        ];
    }
}
