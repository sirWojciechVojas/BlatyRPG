<?php

namespace App\Services\Admin;

use App\Services\Profession\ProfessionAssetStorage;
use App\Services\Profession\ProfessionRequirementDecoder;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;

final class AdminProfessionService
{
    private $db;
    private $validator;
    private $decoder;
    private $storage;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?AdminProfessionPayloadValidator $validator = null,
        ?ProfessionRequirementDecoder $decoder = null,
        ?ProfessionAssetStorage $storage = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->validator = $validator ?: new AdminProfessionPayloadValidator();
        $this->decoder = $decoder ?: new ProfessionRequirementDecoder($this->db);
        $this->storage = $storage ?: new ProfessionAssetStorage();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function overview(array $auth): array
    {
        $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $rows = $this->db->table('professions p')
            ->select('p.*,s.code AS system_code,s.name AS system_name')
            ->join('rpg_systems s', 's.id=p.system_id', 'left')
            ->orderBy('s.name', 'ASC')
            ->orderBy('p.name', 'ASC')
            ->orderBy('p.id', 'ASC')
            ->get()
            ->getResultArray();
        $usage = $this->groupedCounts('character_professions', 'profession_id');
        $references = $this->groupedCounts('profession_paths', 'related_profession_id');
        $ids = array_map('intval', array_column($rows, 'id'));
        $requirements = $this->requirementsForIds($ids);
        $assets = $this->assetsForIds($ids);

        $systems = array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'code' => (string) ($row['code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
            ],
            $this->db->table('rpg_systems')
                ->select('id,code,name')
                ->orderBy('name', 'ASC')
                ->orderBy('id', 'ASC')
                ->get()
                ->getResultArray()
        );

        return [
            'items' => array_map(
                fn (array $row): array => $this->present(
                    $row,
                    $usage[(int) $row['id']] ?? 0,
                    $references[(int) $row['id']] ?? 0,
                    $requirements[(int) $row['id']] ?? [],
                    $assets[(int) $row['id']] ?? []
                ),
                $rows
            ),
            'systems' => $systems,
            'requirementOptions' => $this->requirementOptions($systems),
        ];
    }

    public function create(array $auth, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $validated = $this->validator->create($payload);
        $this->assertValid($validated);
        $this->requireSystem((int) $validated['data']['system_id']);
        $timestamp = date('Y-m-d H:i:s');
        $data = $validated['data'] + [
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ];
        $this->db->transBegin();
        try {
            if (!$this->db->table('professions')->insert($data)) {
                throw new AdminException(
                    'profession_write_failed',
                    'The profession could not be created.',
                    500
                );
            }
            $id = (int) $this->db->insertID();
            $this->syncRequirements(
                $id,
                (int) $validated['data']['system_id'],
                $validated['definitions'] ?? []
            );
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof AdminException) throw $exception;
            throw new AdminException(
                'profession_write_failed',
                'The profession could not be created.',
                500
            );
        }
        return ['profession' => $this->presentOne($id)];
    }

    public function decodeRequirements(array $auth, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $systemId = filter_var(
            $payload['systemId'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($systemId === false) {
            throw new AdminException(
                'validation_failed',
                'A valid RPG system is required.',
                422,
                ['systemId' => 'A valid RPG system is required.']
            );
        }
        $this->requireSystem((int) $systemId);
        $result = [];
        foreach (['skills', 'talents'] as $type) {
            $raw = $payload[$type] ?? '';
            if (!is_string($raw) || strlen($raw) > 65535) {
                throw new AdminException(
                    'validation_failed',
                    'Requirement expression is invalid.',
                    422,
                    [$type => 'Requirement expression must not exceed 65535 bytes.']
                );
            }
            $result[$type] = $this->decoder->decode(
                $raw,
                $type,
                (int) $systemId
            );
        }
        return $result;
    }

    public function update(array $auth, int $professionId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $current = $this->find($professionId);
        $this->assertCurrent($current, $validated['updatedAt']);
        $data = $validated['data'];
        $data['updated_at'] = $this->nextTimestamp($current['updated_at'] ?? null);

        $this->db->transBegin();
        try {
            $builder = $this->db->table('professions')
                ->where('id', $professionId);
            $this->whereTimestamp($builder, $validated['updatedAt']);
            $builder->update($data);
            if ($this->db->affectedRows() !== 1) {
                $this->throwConflict($professionId);
            }
            $this->syncRequirements(
                $professionId,
                (int) $current['system_id'],
                $validated['definitions'] ?? []
            );
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof AdminException) throw $exception;
            throw new AdminException(
                'profession_write_failed',
                'The profession could not be updated.',
                500
            );
        }
        return ['profession' => $this->presentOne($professionId)];
    }

    public function delete(array $auth, int $professionId, array $payload): array
    {
        $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $current = $this->find($professionId);
        $this->assertCurrent($current, $validated['updatedAt']);
        $usage = $this->countWhere('character_professions', 'profession_id', $professionId);
        $references = $this->countWhere('profession_paths', 'related_profession_id', $professionId);
        if ($usage > 0 || $references > 0) {
            throw new AdminException(
                'profession_in_use',
                'A profession used by characters or other profession paths cannot be deleted.',
                409,
                [
                    'characterUsage' => $usage,
                    'pathReferences' => $references,
                ]
            );
        }

        $assetKeys = $this->assetKeys($professionId);
        $this->db->transBegin();
        try {
            foreach ([
                'profession_attributes',
                'profession_definitions',
                'profession_equipment',
                'profession_paths',
                'profession_assets',
            ] as $table) {
                if ($this->db->tableExists($table)) {
                    $this->db->table($table)
                        ->where('profession_id', $professionId)
                        ->delete();
                }
            }
            $builder = $this->db->table('professions')
                ->where('id', $professionId);
            $this->whereTimestamp($builder, $validated['updatedAt']);
            $builder->delete();
            if ($this->db->affectedRows() !== 1) {
                throw new AdminException(
                    'profession_changed',
                    'The profession changed after it was loaded.',
                    409
                );
            }
            if ($this->db->transStatus() === false) {
                throw new AdminException(
                    'profession_write_failed',
                    'The profession could not be deleted.',
                    500
                );
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof AdminException) {
                throw $exception;
            }
            throw new AdminException(
                'profession_write_failed',
                'The profession could not be deleted.',
                500
            );
        }

        foreach ($assetKeys as $key) {
            $this->storage->discard($key);
        }

        return ['deleted' => true, 'id' => $professionId];
    }

    public function uploadImage(
        array $auth,
        int $professionId,
        string $slot,
        ?UploadedFile $file
    ): array {
        $admin = $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $this->requireAssets();
        $slot = $this->validSlot($slot);
        $this->find($professionId);
        try {
            $stored = $this->storage->store($file);
        } catch (\InvalidArgumentException $exception) {
            throw new AdminException(
                'invalid_profession_asset',
                $exception->getMessage(),
                422,
                ['file' => $exception->getMessage()]
            );
        } catch (\Throwable $exception) {
            throw new AdminException(
                'profession_asset_storage_failed',
                $exception->getMessage(),
                500
            );
        }
        $central = null;
        if ($this->db->tableExists('media_assets')
            && $this->db->fieldExists('media_asset_id', 'profession_assets')) {
            try {
                $central = $this->media->uploadFile($auth, $this->storage->path($stored['storage_key']), [
                    'filename' => $stored['original_name'], 'mimeType' => $stored['mime_type'],
                    'category' => 'characters', 'visibility' => 'private',
                    'ownerUserId' => (int) $admin['id'],
                ]);
            } catch (MediaException $exception) {
                $this->storage->discard((string) $stored['storage_key']);
                throw new AdminException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
        }

        $old = $this->db->table('profession_assets')
            ->where('profession_id', $professionId)
            ->where('slot', $slot)
            ->get()->getRowArray();
        $this->db->transBegin();
        try {
            if ($old) {
                $this->db->table('profession_assets')
                    ->where('id', (int) $old['id'])->delete();
            }
            $data = $stored + [
                'profession_id' => $professionId,
                'slot' => $slot,
                'created_by_user_id' => (int) $admin['id'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            if ($central) {
                $data['storage_key'] = null;
                $data['media_asset_id'] = (int) $central['id'];
            }
            if (!$this->db->table('profession_assets')->insert($data)) {
                throw new AdminException(
                    'profession_asset_storage_failed',
                    'Profession image metadata could not be stored.',
                    500
                );
            }
            $this->touch($professionId);
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            $this->storage->discard((string) $stored['storage_key']);
            if ($exception instanceof AdminException) throw $exception;
            throw new AdminException(
                'profession_asset_storage_failed',
                'The profession image could not be stored.',
                500
            );
        }
        if ($old) {
            if (!empty($old['storage_key'])) $this->storage->discard((string) $old['storage_key']);
        }
        if ($central) $this->storage->discard((string) $stored['storage_key']);
        return ['profession' => $this->presentOne($professionId)];
    }

    public function deleteImage(
        array $auth,
        int $professionId,
        string $slot
    ): array {
        $this->verifiedAdmin($auth);
        $this->ensureCatalog();
        $this->requireAssets();
        $slot = $this->validSlot($slot);
        $this->find($professionId);
        $row = $this->db->table('profession_assets')
            ->where('profession_id', $professionId)
            ->where('slot', $slot)
            ->get()->getRowArray();
        if ($row) {
            $this->db->transBegin();
            try {
                $this->db->table('profession_assets')
                    ->where('id', (int) $row['id'])->delete();
                $this->touch($professionId);
                $this->finishTransaction();
            } catch (\Throwable $exception) {
                $this->db->transRollback();
                if ($exception instanceof AdminException) throw $exception;
                throw new AdminException(
                    'profession_asset_storage_failed',
                    'The profession image could not be deleted.',
                    500
                );
            }
            if (!empty($row['storage_key'])) $this->storage->discard((string) $row['storage_key']);
        }
        return ['profession' => $this->presentOne($professionId)];
    }

    private function presentOne(int $professionId): array
    {
        $row = $this->db->table('professions p')
            ->select('p.*,s.code AS system_code,s.name AS system_name')
            ->join('rpg_systems s', 's.id=p.system_id', 'left')
            ->where('p.id', $professionId)
            ->get()
            ->getRowArray();
        if (!$row) {
            throw new AdminException(
                'profession_not_found',
                'The profession was not found.',
                404
            );
        }
        return $this->present(
            $row,
            $this->countWhere('character_professions', 'profession_id', $professionId),
            $this->countWhere('profession_paths', 'related_profession_id', $professionId),
            $this->requirementsForIds([$professionId])[$professionId] ?? [],
            $this->assetsForIds([$professionId])[$professionId] ?? []
        );
    }

    private function present(
        array $row,
        int $characterUsage,
        int $pathReferences,
        array $requirements = [],
        array $assets = []
    ): array {
        return [
            'id' => (int) $row['id'],
            'systemId' => (int) $row['system_id'],
            'systemCode' => (string) ($row['system_code'] ?? ''),
            'systemName' => (string) ($row['system_name'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'description' => $row['description'] !== null
                ? (string) $row['description'] : null,
            'details' => $row['details'] !== null
                ? (string) $row['details'] : null,
            'isAdvanced' => $this->boolean($row['is_advanced'] ?? false),
            'isMain' => $this->boolean($row['is_main'] ?? false),
            'skills' => $requirements['skills']['raw'] ?? '',
            'skillsDecoded' => $requirements['skills']['display'] ?? '',
            'skillsFullyDecoded' => $requirements['skills']['decoded'] ?? true,
            'skillItems' => $requirements['skills']['items'] ?? [],
            'talents' => $requirements['talents']['raw'] ?? '',
            'talentsDecoded' => $requirements['talents']['display'] ?? '',
            'talentsFullyDecoded' => $requirements['talents']['decoded'] ?? true,
            'talentItems' => $requirements['talents']['items'] ?? [],
            'images' => [
                'male' => $assets['male'] ?? null,
                'female' => $assets['female'] ?? null,
            ],
            'createdAt' => $row['created_at'] ?? null,
            'updatedAt' => $row['updated_at'] ?? null,
            'usage' => [
                'characters' => $characterUsage,
                'pathReferences' => $pathReferences,
            ],
            'canDelete' => $characterUsage === 0 && $pathReferences === 0,
        ];
    }

    private function requirementsForIds(array $professionIds): array
    {
        if (!$professionIds || !$this->db->tableExists('profession_definitions')) {
            return [];
        }
        $systems = [];
        foreach ($this->db->table('professions')
            ->select('id,system_id')->whereIn('id', $professionIds)
            ->get()->getResultArray() as $row) {
            $systems[(int) $row['id']] = (int) $row['system_id'];
        }
        $result = [];
        $rows = $this->db->table('profession_definitions')
            ->select('profession_id,definition_id,metadata')
            ->whereIn('profession_id', $professionIds)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $metadata = $this->jsonObject($row['metadata'] ?? null);
            $type = strtolower((string) ($metadata['list_type'] ?? ''));
            if (!in_array($type, ['skills', 'talents'], true)
                || !array_key_exists('raw', $metadata)) {
                continue;
            }
            $id = (int) $row['profession_id'];
            if (isset($result[$id][$type])) continue;
            $result[$id][$type] = $this->decoder->decode(
                (string) $metadata['raw'],
                $type,
                $systems[$id] ?? 0
            );
        }
        return $result;
    }

    private function syncRequirements(
        int $professionId,
        int $systemId,
        array $definitions
    ): void {
        if (!$definitions || !$this->db->tableExists('profession_definitions')) {
            return;
        }
        $rows = $this->db->table('profession_definitions')
            ->where('profession_id', $professionId)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($definitions as $type => $raw) {
            if (!in_array($type, ['skills', 'talents'], true)) continue;
            $target = null;
            foreach ($rows as $row) {
                $metadata = $this->jsonObject($row['metadata'] ?? null);
                if (($metadata['list_type'] ?? null) === $type
                    && empty($row['definition_id'])) {
                    $target = $row;
                    break;
                }
            }
            $raw = trim((string) $raw);
            if ($raw === '') {
                if ($target) {
                    $this->db->table('profession_definitions')
                        ->where('id', (int) $target['id'])->delete();
                }
                continue;
            }
            $decoded = $this->decoder->decode($raw, $type, $systemId);
            $metadata = $target
                ? $this->jsonObject($target['metadata'] ?? null)
                : [];
            $metadata = array_merge($metadata, [
                'list_type' => $type,
                'raw' => $raw,
                'display' => $decoded['display'],
                'decoded' => $decoded['decoded'],
            ]);
            $data = [
                'profession_id' => $professionId,
                'definition_id' => null,
                'metadata' => json_encode(
                    $metadata,
                    JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
                ),
            ];
            if ($target) {
                $this->db->table('profession_definitions')
                    ->where('id', (int) $target['id'])->update($data);
            } else {
                $this->db->table('profession_definitions')->insert($data);
            }
        }
    }

    private function requirementOptions(array $systems): array
    {
        $result = ['skills' => [], 'talents' => []];
        foreach ($systems as $system) {
            $systemId = (int) $system['id'];
            foreach (['skills', 'talents'] as $type) {
                foreach ($this->decoder->options($type, $systemId) as $option) {
                    $result[$type][] = $option + ['systemId' => $systemId];
                }
            }
        }
        return $result;
    }

    private function assetsForIds(array $professionIds): array
    {
        if (!$professionIds || !$this->db->tableExists('profession_assets')) {
            return [];
        }
        $result = [];
        $rows = $this->db->table('profession_assets')
            ->whereIn('profession_id', $professionIds)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            $id = (int) $row['profession_id'];
            $slot = (string) $row['slot'];
            $result[$id][$slot] = $this->presentAsset($row);
        }
        return $result;
    }

    private function presentAsset(array $row): array
    {
        $id = (int) $row['id'];
        return [
            'id' => $id,
            'slot' => (string) $row['slot'],
            'url' => '/api/profession-assets/' . $id . '/file',
            'name' => (string) $row['original_name'],
            'mimeType' => (string) $row['mime_type'],
            'byteSize' => (int) $row['byte_size'],
            'width' => (int) $row['width'],
            'height' => (int) $row['height'],
        ];
    }

    private function assetKeys(int $professionId): array
    {
        if (!$this->db->tableExists('profession_assets')) return [];
        return array_map(
            static fn (array $row): string => (string) ($row['storage_key'] ?? ''),
            $this->db->table('profession_assets')->select('storage_key')
                ->where('profession_id', $professionId)
                ->where('storage_key IS NOT NULL', null, false)
                ->get()->getResultArray()
        );
    }

    private function validSlot(string $slot): string
    {
        $slot = strtolower(trim($slot));
        if (!in_array($slot, ['male', 'female'], true)) {
            throw new AdminException(
                'invalid_profession_asset_slot',
                'Profession image slot must be male or female.',
                422
            );
        }
        return $slot;
    }

    private function requireAssets(): void
    {
        if (!$this->db->tableExists('profession_assets')) {
            throw new AdminException(
                'profession_assets_unavailable',
                'Profession image storage is not available.',
                503
            );
        }
    }

    private function touch(int $professionId): void
    {
        $current = $this->find($professionId);
        $this->db->table('professions')->where('id', $professionId)->update([
            'updated_at' => $this->nextTimestamp($current['updated_at'] ?? null),
        ]);
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new AdminException(
                'profession_write_failed',
                'The profession could not be saved.',
                500
            );
        }
        $this->db->transCommit();
    }

    private function jsonObject($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function groupedCounts(string $table, string $field): array
    {
        if (!$this->db->tableExists($table)) {
            return [];
        }
        $rows = $this->db->table($table)
            ->select($field . ',COUNT(*) AS total', false)
            ->groupBy($field)
            ->get()
            ->getResultArray();
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row[$field]] = (int) $row['total'];
        }
        return $result;
    }

    private function countWhere(string $table, string $field, int $id): int
    {
        if (!$this->db->tableExists($table)) {
            return 0;
        }
        return (int) $this->db->table($table)
            ->where($field, $id)
            ->countAllResults();
    }

    private function find(int $professionId): array
    {
        $row = $professionId > 0
            ? $this->db->table('professions')
                ->where('id', $professionId)
                ->get()
                ->getRowArray()
            : null;
        if (!$row) {
            throw new AdminException(
                'profession_not_found',
                'The profession was not found.',
                404
            );
        }
        return $row;
    }

    private function requireSystem(int $systemId): void
    {
        $exists = $this->db->table('rpg_systems')
            ->where('id', $systemId)
            ->countAllResults() > 0;
        if (!$exists) {
            throw new AdminException(
                'rpg_system_not_found',
                'The RPG system was not found.',
                422,
                ['systemId' => 'Select an existing RPG system.']
            );
        }
    }

    private function assertCurrent(array $current, $expected): void
    {
        $actual = $current['updated_at'] ?? null;
        if ($actual !== $expected) {
            throw new AdminException(
                'profession_changed',
                'The profession changed after it was loaded.',
                409,
                ['currentUpdatedAt' => $actual]
            );
        }
    }

    private function throwConflict(int $professionId): void
    {
        $current = $this->find($professionId);
        throw new AdminException(
            'profession_changed',
            'The profession changed after it was loaded.',
            409,
            ['currentUpdatedAt' => $current['updated_at'] ?? null]
        );
    }

    private function whereTimestamp($builder, $updatedAt): void
    {
        $builder->where('updated_at', $updatedAt);
    }

    private function nextTimestamp($current): string
    {
        $currentTime = is_string($current) ? strtotime($current) : false;
        return date('Y-m-d H:i:s', max(
            time(),
            $currentTime === false ? 0 : $currentTime + 1
        ));
    }

    private function ensureCatalog(): void
    {
        if (!$this->db->tableExists('professions')
            || !$this->db->tableExists('rpg_systems')) {
            throw new AdminException(
                'profession_catalog_unavailable',
                'The profession catalog is not available.',
                503
            );
        }
    }

    private function verifiedAdmin(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new AdminException(
                'unauthorized',
                'Authentication is required.',
                401
            );
        }
        $user = $this->db->table('users')
            ->where('id', $userId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();
        if (!$user) {
            throw new AdminException(
                'unauthorized',
                'Authentication is required.',
                401
            );
        }
        if (strtolower((string) $user['role']) !== 'admin') {
            throw new AdminException(
                'forbidden',
                'Administrator access is required.',
                403
            );
        }
        return $user;
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new AdminException(
                'validation_failed',
                'Profession payload is invalid.',
                422,
                $validated['errors']
            );
        }
    }

    private function boolean($value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }
}
