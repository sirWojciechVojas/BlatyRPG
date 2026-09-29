<?php

namespace App\Services\Profession;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Character\CharacterCampaignAssignmentLookup;
use App\Services\Character\CharacterDirectoryService;
use App\Services\Character\CharacterException;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;

final class ProfessionCatalogService
{
    private $db;
    private $campaigns;
    private $characters;
    private $decoder;
    private $storage;
    private $pathResolver;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignGuardService $campaigns = null,
        ?CharacterDirectoryService $characters = null,
        ?ProfessionRequirementDecoder $decoder = null,
        ?ProfessionAssetStorage $storage = null,
        ?ProfessionPathResolver $pathResolver = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->campaigns = $campaigns ?: new CampaignGuardService();
        $this->characters = $characters;
        $this->decoder = $decoder ?: new ProfessionRequirementDecoder($this->db);
        $this->storage = $storage ?: new ProfessionAssetStorage();
        $this->pathResolver = $pathResolver ?: new ProfessionPathResolver();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function campaignCatalog(int $campaignId, array $auth): array
    {
        $context = $this->campaigns->context($auth, $campaignId);
        $systemId = (int) ($context['campaign']['rpg_system_id'] ?? 0);
        if ($systemId < 1) {
            throw new CampaignException(
                'profession_catalog_unavailable',
                'The campaign has no RPG system assigned.',
                409
            );
        }

        $catalog = $this->systemCatalog($systemId);
        $catalog['meta']['campaign_id'] = $campaignId;
        return $catalog;
    }

    public function systemCatalog(int $systemId): array
    {
        $system = $systemId > 0
            ? $this->db->table('rpg_systems')->select('id,code,name')
                ->where('id', $systemId)->get()->getRowArray()
            : null;
        if (!$system) {
            throw new CampaignException(
                'rpg_system_not_found',
                'The RPG system was not found.',
                404
            );
        }
        if (!$this->db->tableExists('professions')) {
            throw new CampaignException(
                'profession_catalog_unavailable',
                'The profession catalog is not available.',
                503
            );
        }

        $rows = $this->db->table('professions')
            ->where('system_id', $systemId)
            ->orderBy('id', 'ASC')
            ->get()
            ->getResultArray();
        $development = $this->developmentByProfession(
            array_map('intval', array_column($rows, 'id')),
            (string) ($system['code'] ?? ''),
            $rows
        );
        $assets = $this->assetsByProfession(array_map(
            'intval',
            array_column($rows, 'id')
        ));

        $items = [];
        $basic = 0;
        $advanced = 0;
        $hasUnverifiedRules = false;
        foreach ($rows as $row) {
            $professionId = (int) $row['id'];
            $isAdvanced = $this->boolean($row['is_advanced'] ?? false);
            $isAdvanced ? $advanced++ : $basic++;
            $rules = $development[$professionId] ?? $this->emptyDevelopment();
            $hasUnverifiedRules = $hasUnverifiedRules
                || $rules['status'] === 'unverified';
            $items[] = [
                'id' => $professionId,
                'system_id' => (int) $row['system_id'],
                'name' => (string) ($row['name'] ?? ''),
                'description' => $row['description'] !== null
                    ? (string) $row['description'] : null,
                'details' => $row['details'] !== null
                    ? (string) $row['details'] : null,
                'is_advanced' => $isAdvanced,
                'is_main' => $this->boolean($row['is_main'] ?? false),
                'created_at' => $row['created_at'] ?? null,
                'updated_at' => $row['updated_at'] ?? null,
                'images' => [
                    'male' => $assets[$professionId]['male'] ?? null,
                    'female' => $assets[$professionId]['female'] ?? null,
                ],
                'development' => $rules,
            ];
        }

        return [
            'items' => $items,
            'meta' => [
                'system_id' => (int) $system['id'],
                'system_code' => (string) ($system['code'] ?? ''),
                'system_name' => (string) ($system['name'] ?? ''),
                'total' => count($items),
                'basic' => $basic,
                'advanced' => $advanced,
                // Legacy mechanics have no source/version verification marker.
                'rules_available' => false,
                'rules_status' => $hasUnverifiedRules
                    ? 'unverified' : 'unavailable',
            ],
        ];
    }

    public function asset(int $assetId, array $auth): array
    {
        if ((int) ($auth['user_id'] ?? 0) < 1 || !empty($auth['anonymous'])) {
            throw new CampaignException(
                'unauthorized',
                'Authentication is required.',
                401
            );
        }
        $row = $assetId > 0 && $this->db->tableExists('profession_assets')
            ? $this->db->table('profession_assets')->where('id', $assetId)
                ->get()->getRowArray()
            : null;
        if (!$row) {
            throw new CampaignException(
                'profession_asset_not_found',
                'Profession image was not found.',
                404
            );
        }
        if (!empty($row['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $row['media_asset_id']);
            } catch (MediaException $exception) {
                throw new CampaignException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['asset' => $row, 'url' => $media['url']];
        }
        try {
            $path = $this->storage->path((string) $row['storage_key']);
        } catch (\Throwable $exception) {
            throw new CampaignException(
                'profession_asset_not_found',
                'Profession image was not found.',
                404
            );
        }
        return ['asset' => $row, 'path' => $path];
    }

    public function characterHistory(
        int $campaignId,
        int $characterId,
        array $auth
    ): array {
        $context = $this->campaigns->context($auth, $campaignId);
        try {
            $characters = $this->characters
                ?: new CharacterDirectoryService($this->db);
            $characterResult = $characters->show(
                $auth,
                $characterId,
                $campaignId
            );
        } catch (CharacterException $exception) {
            throw new CampaignException(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->errors()
            );
        }

        $character = $characterResult['character'] ?? $characterResult;
        $campaignSystemId = (int) ($context['campaign']['rpg_system_id'] ?? 0);
        $characterSystemId = (int) ($character['systemId'] ?? 0);
        $canManageProfession = !empty(
            $context['capabilities']['canManage']
        );
        $historyAvailable = $this->db->tableExists('character_professions');
        if (!$historyAvailable) {
            return $this->emptyCharacterHistory(
                $character,
                false,
                $canManageProfession
            );
        }

        $orderAvailable = $this->hasProfessionOrder();
        $select = 'cp.id,cp.profession_id,cp.is_current,cp.is_finished,'
            . 'cp.started_at,cp.finished_at,'
            . ($orderAvailable ? 'cp.sort_order,' : '')
            . 'p.name,p.system_id,p.is_advanced';
        $builder = $this->db->table('character_professions cp')
            ->select($select)
            ->join('professions p', 'p.id=cp.profession_id', 'left')
            ->where('cp.character_id', $characterId)
            ->orderBy('cp.is_current', 'ASC');
        if ($orderAvailable) {
            $builder->orderBy('cp.sort_order', 'ASC');
        }
        $rows = $builder->orderBy('cp.id', 'ASC')->get()->getResultArray();

        $current = null;
        $history = [];
        foreach ($rows as $row) {
            $professionSystemId = isset($row['system_id'])
                ? (int) $row['system_id'] : null;
            $linked = !empty($row['name'])
                && $professionSystemId === $characterSystemId
                && ($campaignSystemId < 1 || $professionSystemId === $campaignSystemId);
            $item = [
                'historyId' => (int) $row['id'],
                'professionId' => (int) $row['profession_id'],
                'name' => $linked ? (string) $row['name'] : null,
                'linked' => $linked,
                'isAdvanced' => $linked
                    ? $this->boolean($row['is_advanced'] ?? false) : null,
                'isCurrent' => $this->boolean($row['is_current'] ?? false),
                'isFinished' => $this->boolean($row['is_finished'] ?? false),
                'order' => (int) ($row['sort_order'] ?? 0),
                'startedAt' => $row['started_at'] ?? null,
                'finishedAt' => $row['finished_at'] ?? null,
            ];
            if ($item['isCurrent']) {
                $current = $item;
            } else {
                $history[] = $item;
            }
        }

        return [
            'character' => [
                'id' => (int) ($character['id'] ?? $characterId),
                'name' => (string) ($character['name'] ?? ''),
                'systemId' => $characterSystemId,
            ],
            'current' => $current,
            'history' => $history,
            'historyAvailable' => true,
            'capabilities' => [
                'canManageProfession' => $canManageProfession,
                'canReorderProfessionHistory' => $canManageProfession
                    && $orderAvailable,
            ],
        ];
    }

    public function changeCharacterProfession(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $context = $this->campaigns->requireManage($auth, $campaignId);
        $character = $this->managedCharacter(
            $campaignId,
            $characterId,
            $context
        );
        $professionId = filter_var(
            $payload['professionId'] ?? null,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        if ($professionId === false) {
            throw new CampaignException(
                'validation_failed',
                'A valid profession id is required.',
                422,
                ['professionId' => 'required']
            );
        }
        $profession = $this->db->table('professions')
            ->select('id,system_id,name')
            ->where('id', (int) $professionId)->get()->getRowArray();
        $characterSystemId = (int) ($character['system_id'] ?? 0);
        if (!$profession
            || (int) ($profession['system_id'] ?? 0) !== $characterSystemId) {
            throw new CampaignException(
                'profession_not_found',
                'The profession does not belong to the character RPG system.',
                422,
                ['professionId' => 'system_mismatch']
            );
        }
        $this->requireCharacterProfessionStorage();
        $orderAvailable = $this->hasProfessionOrder();

        $currentRows = $this->db->table('character_professions')
            ->where('character_id', $characterId)
            ->where('is_current', 1)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        if (count($currentRows) === 1
            && (int) $currentRows[0]['profession_id'] === (int) $professionId) {
            return [
                'changed' => false,
                'characterId' => $characterId,
                'previousProfessionId' => (int) $professionId,
                'currentProfessionId' => (int) $professionId,
                'xpSpent' => 0,
            ];
        }

        $previousProfessionId = $currentRows
            ? (int) $currentRows[count($currentRows) - 1]['profession_id']
            : null;
        $now = date('Y-m-d H:i:s');
        $nextOrder = $orderAvailable
            ? $this->nextProfessionOrder($characterId) : 0;
        $this->db->transBegin();
        try {
            foreach ($currentRows as $row) {
                $historyUpdate = [
                    'is_current' => 0,
                    'is_finished' => 1,
                    'finished_at' => $now,
                ];
                if ($orderAvailable) {
                    $historyUpdate['sort_order'] = $nextOrder++;
                }
                $this->db->table('character_professions')
                    ->where('id', (int) $row['id'])
                    ->update($historyUpdate);
            }
            $newHistory = [
                'character_id' => $characterId,
                'profession_id' => (int) $professionId,
                'is_current' => 1,
                'is_finished' => 0,
                'started_at' => $now,
                'finished_at' => null,
            ];
            if ($orderAvailable) {
                $newHistory['sort_order'] = $nextOrder;
            }
            $this->db->table('character_professions')->insert($newHistory);
            $this->assertTransactionSucceeded(
                'profession_change_failed',
                'The character profession could not be changed.'
            );
            $historyId = (int) $this->db->insertID();
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof CampaignException) {
                throw $exception;
            }
            throw new CampaignException(
                'profession_change_failed',
                'The character profession could not be changed.',
                500
            );
        }

        return [
            'changed' => true,
            'characterId' => $characterId,
            'historyId' => $historyId,
            'previousProfessionId' => $previousProfessionId,
            'currentProfessionId' => (int) $professionId,
            'professionName' => (string) $profession['name'],
            'xpSpent' => 0,
            'savedAt' => $now,
        ];
    }

    public function reorderCharacterProfessions(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $context = $this->campaigns->requireManage($auth, $campaignId);
        $this->managedCharacter($campaignId, $characterId, $context);
        $this->requireCharacterProfessionStorage();
        if (!$this->hasProfessionOrder()) {
            throw new CampaignException(
                'profession_history_order_unavailable',
                'Profession history ordering is not available yet.',
                503
            );
        }
        $submitted = $payload['historyIds'] ?? null;
        if (!is_array($submitted)) {
            throw new CampaignException(
                'validation_failed',
                'Profession history ids are required.',
                422,
                ['historyIds' => 'required']
            );
        }
        $historyIds = [];
        foreach ($submitted as $value) {
            $id = filter_var(
                $value,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($id === false || in_array((int) $id, $historyIds, true)) {
                throw new CampaignException(
                    'validation_failed',
                    'Profession history ids must be unique positive integers.',
                    422,
                    ['historyIds' => 'invalid']
                );
            }
            $historyIds[] = (int) $id;
        }
        $storedIds = array_map(
            'intval',
            array_column(
                $this->db->table('character_professions')
                    ->select('id')->where('character_id', $characterId)
                    ->where('is_current', 0)->get()->getResultArray(),
                'id'
            )
        );
        $submittedSet = $historyIds;
        sort($submittedSet);
        sort($storedIds);
        if ($submittedSet !== $storedIds) {
            throw new CampaignException(
                'profession_history_changed',
                'Profession history changed before the new order was saved.',
                409
            );
        }

        $this->db->transBegin();
        try {
            foreach ($historyIds as $index => $historyId) {
                $this->db->table('character_professions')
                    ->where('id', $historyId)
                    ->where('character_id', $characterId)
                    ->where('is_current', 0)
                    ->update(['sort_order' => $index + 1]);
            }
            $this->assertTransactionSucceeded(
                'profession_history_reorder_failed',
                'Profession history order could not be saved.'
            );
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof CampaignException) {
                throw $exception;
            }
            throw new CampaignException(
                'profession_history_reorder_failed',
                'Profession history order could not be saved.',
                500
            );
        }

        return [
            'saved' => true,
            'characterId' => $characterId,
            'historyIds' => $historyIds,
        ];
    }

    public function activateCharacterProfession(
        int $campaignId,
        int $characterId,
        int $historyId,
        array $auth
    ): array {
        $context = $this->campaigns->requireManage($auth, $campaignId);
        $character = $this->managedCharacter(
            $campaignId,
            $characterId,
            $context
        );
        $this->requireCharacterProfessionStorage();

        $target = $this->db->table('character_professions cp')
            ->select('cp.*,p.system_id,p.name')
            ->join('professions p', 'p.id=cp.profession_id', 'left')
            ->where('cp.id', $historyId)
            ->where('cp.character_id', $characterId)
            ->get()->getRowArray();
        if (!$target || empty($target['name'])) {
            throw new CampaignException(
                'profession_history_not_found',
                'The profession history entry was not found.',
                404
            );
        }
        if ((int) ($target['system_id'] ?? 0)
            !== (int) ($character['system_id'] ?? 0)) {
            throw new CampaignException(
                'profession_not_found',
                'The profession does not belong to the character RPG system.',
                422
            );
        }
        if ($this->boolean($target['is_current'] ?? false)) {
            return [
                'changed' => false,
                'characterId' => $characterId,
                'historyId' => $historyId,
                'currentProfessionId' => (int) $target['profession_id'],
                'xpSpent' => 0,
            ];
        }

        $currentRows = $this->db->table('character_professions')
            ->where('character_id', $characterId)
            ->where('is_current', 1)
            ->orderBy('id', 'ASC')->get()->getResultArray();
        $previousProfessionId = $currentRows
            ? (int) $currentRows[count($currentRows) - 1]['profession_id']
            : null;
        $orderAvailable = $this->hasProfessionOrder();
        $nextOrder = $orderAvailable
            ? $this->nextProfessionOrder($characterId) : 0;
        $replacementOrder = $orderAvailable
            ? max(1, (int) ($target['sort_order'] ?? 0)) : 0;
        $now = date('Y-m-d H:i:s');

        $this->db->transBegin();
        try {
            foreach ($currentRows as $index => $row) {
                $update = [
                    'is_current' => 0,
                    'is_finished' => 1,
                    'finished_at' => $now,
                ];
                if ($orderAvailable) {
                    $update['sort_order'] = $index === 0
                        ? $replacementOrder : $nextOrder++;
                }
                $this->db->table('character_professions')
                    ->where('id', (int) $row['id'])
                    ->update($update);
            }

            $targetUpdate = [
                'is_current' => 1,
                'is_finished' => 0,
                'finished_at' => null,
            ];
            if ($orderAvailable) {
                $targetUpdate['sort_order'] = $nextOrder;
            }
            $this->db->table('character_professions')
                ->where('id', $historyId)
                ->where('character_id', $characterId)
                ->update($targetUpdate);
            $this->assertTransactionSucceeded(
                'profession_activation_failed',
                'The historical profession could not be activated.'
            );
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            if ($exception instanceof CampaignException) {
                throw $exception;
            }
            throw new CampaignException(
                'profession_activation_failed',
                'The historical profession could not be activated.',
                500
            );
        }

        return [
            'changed' => true,
            'characterId' => $characterId,
            'historyId' => $historyId,
            'previousProfessionId' => $previousProfessionId,
            'currentProfessionId' => (int) $target['profession_id'],
            'professionName' => (string) $target['name'],
            'xpSpent' => 0,
            'savedAt' => $now,
        ];
    }

    public function deleteCharacterProfession(
        int $campaignId,
        int $characterId,
        int $historyId,
        array $auth
    ): array {
        $context = $this->campaigns->requireManage($auth, $campaignId);
        $this->managedCharacter($campaignId, $characterId, $context);
        $this->requireCharacterProfessionStorage();
        $row = $this->db->table('character_professions')
            ->where('id', $historyId)
            ->where('character_id', $characterId)->get()->getRowArray();
        if (!$row) {
            throw new CampaignException(
                'profession_history_not_found',
                'The profession history entry was not found.',
                404
            );
        }
        if ($this->boolean($row['is_current'] ?? false)) {
            throw new CampaignException(
                'current_profession_delete_forbidden',
                'Change the current profession instead of deleting it.',
                409
            );
        }
        if (!$this->db->table('character_professions')
            ->where('id', $historyId)
            ->where('character_id', $characterId)
            ->delete()) {
            throw new CampaignException(
                'profession_history_delete_failed',
                'The profession history entry could not be deleted.',
                500
            );
        }

        return [
            'deleted' => true,
            'characterId' => $characterId,
            'historyId' => $historyId,
        ];
    }

    private function emptyCharacterHistory(
        array $character,
        bool $available,
        bool $canManageProfession = false
    ): array
    {
        return [
            'character' => [
                'id' => (int) ($character['id'] ?? 0),
                'name' => (string) ($character['name'] ?? ''),
                'systemId' => (int) ($character['systemId'] ?? 0),
            ],
            'current' => null,
            'history' => [],
            'historyAvailable' => $available,
            'capabilities' => [
                'canManageProfession' => $canManageProfession,
                'canReorderProfessionHistory' => false,
            ],
        ];
    }

    private function managedCharacter(
        int $campaignId,
        int $characterId,
        array $context
    ): array {
        $character = $this->db->table('characters')
            ->select('id,campaign_id,system_id,name')
            ->where('id', $characterId)->get()->getRowArray();
        $assigned = $character && (new CharacterCampaignAssignmentLookup(
            $this->db
        ))->contains($characterId, $campaignId, $character);
        if (!$character || !$assigned) {
            throw new CampaignException(
                'character_not_found',
                'The character was not found in this campaign.',
                404
            );
        }
        $campaignSystemId = (int) (
            $context['campaign']['rpg_system_id'] ?? 0
        );
        if ($campaignSystemId < 1
            || (int) ($character['system_id'] ?? 0) !== $campaignSystemId) {
            throw new CampaignException(
                'character_system_mismatch',
                'The character and campaign RPG systems do not match.',
                409
            );
        }

        return $character;
    }

    private function requireCharacterProfessionStorage(): void
    {
        if (!$this->db->tableExists('character_professions')) {
            throw new CampaignException(
                'profession_history_unavailable',
                'Profession history storage is not available.',
                503
            );
        }
    }

    private function hasProfessionOrder(): bool
    {
        return $this->db->tableExists('character_professions')
            && $this->db->fieldExists('sort_order', 'character_professions');
    }

    private function nextProfessionOrder(int $characterId): int
    {
        $row = $this->db->table('character_professions')
            ->selectMax('sort_order', 'maximum_order')
            ->where('character_id', $characterId)
            ->where('is_current', 0)->get()->getRowArray();
        return max(0, (int) ($row['maximum_order'] ?? 0)) + 1;
    }

    private function assertTransactionSucceeded(
        string $code,
        string $message
    ): void {
        if ($this->db->transStatus() === false) {
            throw new CampaignException($code, $message, 500);
        }
    }

    private function developmentByProfession(
        array $professionIds,
        string $systemCode,
        array $professions
    ): array
    {
        if (!$professionIds) {
            return [];
        }
        $result = [];
        foreach ($professionIds as $professionId) {
            $result[$professionId] = $this->emptyDevelopment();
        }
        $professionSystems = [];
        foreach ($this->db->table('professions')->select('id,system_id')
            ->whereIn('id', $professionIds)->get()->getResultArray() as $row) {
            $professionSystems[(int) $row['id']] = (int) $row['system_id'];
        }

        if ($this->db->tableExists('profession_attributes')) {
            $rows = $this->db->table('profession_attributes')
                ->whereIn('profession_id', $professionIds)
                ->orderBy('id', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $id = (int) $row['profession_id'];
                $result[$id]['attributes'][] = [
                    'key' => (string) ($row['attribute_key'] ?? ''),
                    'group' => (string) ($row['attribute_group'] ?? ''),
                    'value' => (int) ($row['value'] ?? 0),
                ];
            }
        }

        if ($this->db->tableExists('profession_definitions')) {
            $builder = $this->db->table('profession_definitions pd')
                ->select('pd.profession_id,pd.definition_id,pd.metadata');
            if ($this->db->tableExists('game_definitions')) {
                $builder->select('gd.name AS definition_name,gd.category')
                    ->join(
                        'game_definitions gd',
                        'gd.id=pd.definition_id',
                        'left'
                    );
            }
            $rows = $builder->whereIn('pd.profession_id', $professionIds)
                ->orderBy('pd.id', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $id = (int) $row['profession_id'];
                $metadata = $this->jsonObject($row['metadata'] ?? null);
                $type = strtolower((string) ($metadata['list_type'] ?? 'other'));
                $category = strtolower((string) ($row['category'] ?? ''));
                $bucket = $type === 'skills' || $category === 'umiejetnosc'
                    ? 'skills'
                    : (($type === 'talents' || $category === 'zdolnosc')
                        ? 'talents' : 'definitions');
                $result[$id][$bucket][] = [
                    'definitionId' => !empty($row['definition_id'])
                        ? (int) $row['definition_id'] : null,
                    'name' => $row['definition_name'] ?? null,
                    'raw' => (string) ($metadata['raw'] ?? ''),
                    'display' => $this->definitionDisplay(
                        (string) ($metadata['raw'] ?? ''),
                        $bucket,
                        $professionSystems[$id] ?? 0
                    ),
                    'metadata' => $metadata,
                ];
            }
        }

        if ($this->db->tableExists('profession_paths')) {
            $rows = $this->db->table('profession_paths pp')
                ->select('pp.profession_id,pp.related_profession_id,'
                    . 'pp.relation_type,p.name')
                ->join('professions p', 'p.id=pp.related_profession_id', 'left')
                ->whereIn('pp.profession_id', $professionIds)
                ->orderBy('pp.id', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $id = (int) $row['profession_id'];
                $bucket = strtolower((string) $row['relation_type']) === 'entry'
                    ? 'entries' : 'exits';
                $result[$id]['paths'][$bucket][] = [
                    'professionId' => (int) $row['related_profession_id'],
                    'name' => (string) ($row['name'] ?? ''),
                    'linked' => !empty($row['name']),
                ];
            }
        }

        $legacyPaths = $this->pathResolver->legacyPaths(
            $systemCode,
            $professions
        );
        foreach ($legacyPaths as $professionId => $paths) {
            if (!isset($result[$professionId])) {
                continue;
            }
            foreach (['entries', 'exits'] as $bucket) {
                $result[$professionId]['paths'][$bucket] = $this->mergePaths(
                    $result[$professionId]['paths'][$bucket],
                    $paths[$bucket] ?? []
                );
            }
        }

        if ($this->db->tableExists('profession_equipment')) {
            $builder = $this->db->table('profession_equipment pe')
                ->select('pe.*');
            if ($this->db->tableExists('game_definitions')) {
                $builder->select('gd.name AS definition_name')
                    ->join(
                        'game_definitions gd',
                        'gd.id=pe.definition_id',
                        'left'
                    );
            }
            $rows = $builder->whereIn('pe.profession_id', $professionIds)
                ->orderBy('pe.id', 'ASC')->get()->getResultArray();
            foreach ($rows as $row) {
                $id = (int) $row['profession_id'];
                $result[$id]['equipment'][] = [
                    'definitionId' => !empty($row['definition_id'])
                        ? (int) $row['definition_id'] : null,
                    'name' => !empty($row['definition_name'])
                        ? (string) $row['definition_name']
                        : ($row['item_name'] !== null
                            ? (string) $row['item_name'] : null),
                    'quantity' => max(1, (int) ($row['quantity'] ?? 1)),
                    'notes' => $row['notes'] !== null
                        ? (string) $row['notes'] : null,
                ];
            }
        }

        foreach ($result as &$development) {
            if ($this->hasDevelopmentData($development)) {
                $development['status'] = 'unverified';
            }
        }
        unset($development);
        return $result;
    }

    private function definitionDisplay(
        string $raw,
        string $type,
        int $systemId
    ): string {
        if (!in_array($type, ['skills', 'talents'], true) || $raw === '') {
            return $raw;
        }
        return $this->decoder->decode(
            $raw,
            $type,
            $systemId
        )['display'];
    }

    private function assetsByProfession(array $professionIds): array
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
            $assetId = (int) $row['id'];
            $result[$id][$slot] = [
                'id' => $assetId,
                'slot' => $slot,
                'url' => '/api/profession-assets/' . $assetId . '/file',
                'width' => (int) $row['width'],
                'height' => (int) $row['height'],
            ];
        }
        return $result;
    }

    private function emptyDevelopment(): array
    {
        return [
            'status' => 'unavailable',
            'attributes' => [],
            'skills' => [],
            'talents' => [],
            'definitions' => [],
            'equipment' => [],
            'paths' => ['entries' => [], 'exits' => []],
        ];
    }

    private function hasDevelopmentData(array $development): bool
    {
        return !empty($development['attributes'])
            || !empty($development['skills'])
            || !empty($development['talents'])
            || !empty($development['definitions'])
            || !empty($development['equipment'])
            || !empty($development['paths']['entries'])
            || !empty($development['paths']['exits']);
    }

    private function mergePaths(array $stored, array $legacy): array
    {
        $result = [];
        $seen = [];
        foreach (array_merge($stored, $legacy) as $item) {
            $professionId = isset($item['professionId'])
                ? (int) $item['professionId'] : null;
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $key = $professionId
                ? 'id:' . $professionId
                : 'name:' . strtolower($name);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $result[] = [
                'professionId' => $professionId,
                'name' => $name,
                'linked' => $professionId !== null
                    && ($item['linked'] ?? true),
            ];
        }

        return $result;
    }

    private function boolean($value): bool
    {
        return in_array($value, [true, 1, '1'], true);
    }

    private function jsonObject($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = is_string($value) ? json_decode($value, true) : null;
        return is_array($decoded) ? $decoded : [];
    }
}
