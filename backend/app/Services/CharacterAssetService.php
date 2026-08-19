<?php

namespace App\Services;

use App\Models\CharacterAssetModel;
use App\Models\CharacterAssetSetModel;
use App\Models\CharacterModel;
use Config\Database;
use InvalidArgumentException;

final class CharacterAssetService
{
    private $db;
    private $assetSetModel;
    private $assetModel;
    private $characterModel;
    private $cloudinary;

    public function __construct(?CloudinaryCharacterAssetService $cloudinary = null)
    {
        $this->db = Database::connect();
        $this->assetSetModel = new CharacterAssetSetModel();
        $this->assetModel = new CharacterAssetModel();
        $this->characterModel = new CharacterModel();
        $this->cloudinary = $cloudinary ?? new CloudinaryCharacterAssetService();
    }

    public function availableSets(): array
    {
        if (!$this->schemaReady()) {
            return [];
        }

        $sets = $this->assetSetModel
            ->where('status', CharacterAssetSetModel::STATUS_AVAILABLE)
            ->orderBy('name', 'ASC')
            ->orderBy('id', 'ASC')
            ->findAll();

        return array_values(array_filter(
            $this->hydrateSets($sets),
            static fn (array $set): bool => count($set['assets'] ?? []) === count(CharacterAssetModel::TYPES)
        ));
    }

    public function assetsForCharacter(int $characterId): ?array
    {
        $character = $this->characterModel->find($characterId);
        if (!$character) {
            return null;
        }

        $setId = (int) ($character['asset_set_id'] ?? 0);
        return [
            'characterId' => $characterId,
            'assetSet' => $setId > 0 ? $this->setById($setId) : null,
        ];
    }

    public function setById(int $assetSetId): ?array
    {
        if (!$this->schemaReady() || $assetSetId < 1) {
            return null;
        }
        $set = $this->assetSetModel->find($assetSetId);
        return $set ? $this->hydrateSets([$set])[0] : null;
    }

    public function hydrateCharacters(array $characters): array
    {
        if (!$characters || !$this->schemaReady()) {
            return $characters;
        }

        $setIds = array_values(array_unique(array_filter(array_map(
            static fn (array $character): int => (int) ($character['asset_set_id'] ?? 0),
            $characters
        ))));
        $setsById = [];
        if ($setIds) {
            $sets = $this->hydrateSets($this->assetSetModel->whereIn('id', $setIds)->findAll());
            foreach ($sets as $set) {
                $setsById[(int) $set['id']] = $set;
            }
        }

        foreach ($characters as &$character) {
            $setId = (int) ($character['asset_set_id'] ?? 0);
            $set = $setsById[$setId] ?? null;
            $character['assetSetId'] = $setId ?: null;
            $character['assetSet'] = $set;
            $character['assets'] = $set['assets'] ?? [];
        }
        unset($character);
        return $characters;
    }

    public function createAvailableSet(string $name = '', array $publicIds = []): array
    {
        if (!$this->schemaReady()) {
            throw new InvalidArgumentException('Character asset schema is not available.');
        }

        $name = trim($name);
        if (mb_strlen($name) > 150) {
            throw new InvalidArgumentException('Asset set name is too long.');
        }
        $unknownTypes = array_diff(array_keys($publicIds), CharacterAssetModel::TYPES);
        if ($unknownTypes) {
            throw new InvalidArgumentException('Unsupported character asset type.');
        }

        $this->db->transBegin();
        try {
            if (!$this->assetSetModel->insert([
                'name' => $name !== '' ? $name : null,
                'status' => CharacterAssetSetModel::STATUS_AVAILABLE,
            ])) {
                throw new InvalidArgumentException('Could not create the character asset set.');
            }
            $assetSetId = (int) $this->assetSetModel->getInsertID();

            foreach (CharacterAssetModel::TYPES as $type) {
                $publicId = isset($publicIds[$type])
                    ? $this->cloudinary->normalizePublicId((string) $publicIds[$type])
                    : $this->cloudinary->canonicalPublicId($assetSetId, $type);
                if ($publicId === '') {
                    throw new InvalidArgumentException('A public ID cannot be empty.');
                }
                if (!$this->assetModel->insert([
                    'asset_set_id' => $assetSetId,
                    'type' => $type,
                    'public_id' => $publicId,
                ])) {
                    throw new InvalidArgumentException('Could not create a character asset.');
                }
            }

            if ($this->db->transStatus() === false) {
                throw new InvalidArgumentException('Could not create the character asset set.');
            }
            $this->db->transCommit();
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $this->setById($assetSetId) ?? [];
    }

    public function assignSetToCharacter(
        int $characterId,
        int $assetSetId,
        bool $manageTransaction = true
    ): array {
        if (!$this->schemaReady()) {
            return $this->failure(503, 'character_assets_unavailable');
        }
        if ($characterId < 1 || $assetSetId < 1) {
            return $this->failure(422, 'invalid_asset_set_assignment');
        }

        if ($manageTransaction) {
            $this->db->transBegin();
        }
        try {
            // Lock the character first, then every involved set by ascending ID.
            $character = $this->db->query(
                'SELECT id, asset_set_id FROM characters WHERE id = ? FOR UPDATE',
                [$characterId]
            )->getRowArray();
            if (!$character) {
                return $this->rollbackFailure($manageTransaction, 404, 'character_not_found');
            }
            $formerSetId = (int) ($character['asset_set_id'] ?? 0);
            $setIds = array_values(array_unique(array_filter([$assetSetId, $formerSetId])));
            sort($setIds, SORT_NUMERIC);
            $lockedSets = $this->db->query(
                'SELECT id, status FROM character_asset_sets WHERE id IN ('
                . implode(',', array_fill(0, count($setIds), '?'))
                . ') ORDER BY id ASC FOR UPDATE',
                $setIds
            )->getResultArray();
            $setsById = [];
            foreach ($lockedSets as $lockedSet) {
                $setsById[(int) $lockedSet['id']] = $lockedSet;
            }
            $targetSet = $setsById[$assetSetId] ?? null;
            if (!$targetSet) {
                return $this->rollbackFailure($manageTransaction, 404, 'asset_set_not_found');
            }
            if ($targetSet['status'] === CharacterAssetSetModel::STATUS_DISABLED) {
                return $this->rollbackFailure($manageTransaction, 409, 'asset_set_disabled');
            }

            $assignedCharacter = $this->db->table('characters')
                ->select('id')
                ->where('asset_set_id', $assetSetId)
                ->where('id !=', $characterId)
                ->get()
                ->getRowArray();
            if ($assignedCharacter) {
                return $this->rollbackFailure($manageTransaction, 409, 'asset_set_already_assigned');
            }

            $targetAssets = $this->db->table('character_assets')
                ->select('type, public_id')
                ->where('asset_set_id', $assetSetId)
                ->get()
                ->getResultArray();
            $targetAssetsByType = [];
            foreach ($targetAssets as $targetAsset) {
                $targetAssetsByType[(string) $targetAsset['type']] = (string) $targetAsset['public_id'];
            }
            if (count(array_intersect_key(
                $targetAssetsByType,
                array_flip(CharacterAssetModel::TYPES)
            )) !== count(CharacterAssetModel::TYPES)) {
                return $this->rollbackFailure($manageTransaction, 409, 'asset_set_incomplete');
            }

            $this->db->table('characters')->where('id', $characterId)->update([
                'asset_set_id' => $assetSetId,
                'avatar' => $targetAssetsByType['avatar'],
                'avatar_url' => $targetAssetsByType['avatar'],
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->db->table('character_asset_sets')->where('id', $assetSetId)->update([
                'status' => CharacterAssetSetModel::STATUS_ASSIGNED,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            if (
                $formerSetId > 0
                && $formerSetId !== $assetSetId
                && ($setsById[$formerSetId]['status'] ?? null) !== CharacterAssetSetModel::STATUS_DISABLED
            ) {
                $this->db->table('character_asset_sets')->where('id', $formerSetId)->update([
                    'status' => CharacterAssetSetModel::STATUS_AVAILABLE,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }

            if ($this->db->transStatus() === false) {
                return $this->rollbackFailure($manageTransaction, 500, 'asset_set_assignment_failed');
            }
            if ($manageTransaction) {
                $this->db->transCommit();
            }
            return [
                'ok' => true,
                'status' => 200,
                'characterId' => $characterId,
                'assetSet' => $this->setById($assetSetId),
            ];
        } catch (\Throwable $error) {
            if ($manageTransaction) {
                $this->db->transRollback();
            }
            throw $error;
        }
    }

    public function releaseCharacterSet(int $characterId, bool $manageTransaction = true): void
    {
        if (!$this->schemaReady()) {
            return;
        }
        if ($manageTransaction) {
            $this->db->transBegin();
        }
        $character = $this->db->query(
            'SELECT asset_set_id FROM characters WHERE id = ? FOR UPDATE',
            [$characterId]
        )->getRowArray();
        $setId = (int) ($character['asset_set_id'] ?? 0);
        if ($setId > 0) {
            $set = $this->db->query(
                'SELECT status FROM character_asset_sets WHERE id = ? FOR UPDATE',
                [$setId]
            )->getRowArray();
            $this->db->table('characters')->where('id', $characterId)->update(['asset_set_id' => null]);
            if (($set['status'] ?? null) !== CharacterAssetSetModel::STATUS_DISABLED) {
                $this->db->table('character_asset_sets')->where('id', $setId)->update([
                    'status' => CharacterAssetSetModel::STATUS_AVAILABLE,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
            }
        }
        if ($manageTransaction) {
            $this->db->transCommit();
        }
    }

    private function hydrateSets(array $sets): array
    {
        if (!$sets) {
            return [];
        }
        $setIds = array_map(static fn (array $set): int => (int) $set['id'], $sets);
        $assetsBySet = [];
        foreach ($this->assetModel->whereIn('asset_set_id', $setIds)->orderBy('id', 'ASC')->findAll() as $asset) {
            $type = (string) $asset['type'];
            $publicId = (string) $asset['public_id'];
            $assetsBySet[(int) $asset['asset_set_id']][$type] = [
                'type' => $type,
                'publicId' => $publicId,
                'url' => $this->cloudinary->url($publicId, $type),
            ];
        }
        foreach ($sets as &$set) {
            $set['id'] = (int) $set['id'];
            $set['assets'] = $assetsBySet[$set['id']] ?? [];
        }
        unset($set);
        return $sets;
    }

    private function schemaReady(): bool
    {
        return $this->db->tableExists('character_asset_sets')
            && $this->db->tableExists('character_assets')
            && $this->db->fieldExists('asset_set_id', 'characters');
    }

    private function rollbackFailure(bool $manageTransaction, int $status, string $code): array
    {
        if ($manageTransaction) {
            $this->db->transRollback();
        }
        return $this->failure($status, $code);
    }

    private function failure(int $status, string $code): array
    {
        return ['ok' => false, 'status' => $status, 'code' => $code];
    }
}
