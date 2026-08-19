<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Auth\AuthContextService;
use App\Services\Character\CharacterDirectoryService;
use App\Services\Character\CharacterException;
use App\Services\Character\CharacterLegacyListService;
use App\Services\CharacterAssetService;
use App\Services\CharacterService;
use CodeIgniter\API\ResponseTrait;

class CharacterController extends BaseController
{
    use ResponseTrait;

    private $authContext;
    private $characters;
    private $legacyCharacters;
    private $development;
    private $assets;

    public function __construct()
    {
        $this->authContext = new AuthContextService();
        $this->characters = new CharacterDirectoryService();
        $this->legacyCharacters = new CharacterLegacyListService($this->characters);
        $this->development = new CharacterService();
        $this->assets = new CharacterAssetService();
    }

    /** GET /api/characters?campaignId=1 */
    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            $scope = $campaignId === null
                ? $this->campaignId()
                : $this->positiveId($campaignId);
            $auth = $this->auth();
            $filters = $this->characterFilters();
            return $scope === null
                ? $this->legacyCharacters->list($auth, $filters)
                : $this->characters->list($auth, $scope, $filters);
        });
    }

    /** GET /api/characters/{id}?campaignId=1 */
    public function show($id = null)
    {
        return $this->execute(function () use ($id): array {
            return $this->characters->show($this->auth(), $this->positiveId($id), $this->campaignId());
        });
    }

    /** POST /api/characters */
    public function create()
    {
        return $this->execute(function (): array {
            return $this->characters->create($this->auth(), $this->jsonPayload());
        }, 201);
    }

    /** PUT/PATCH /api/characters/{id}?campaignId=1 */
    public function update($id = null)
    {
        return $this->execute(function () use ($id): array {
            return $this->characters->update(
                $this->auth(),
                $this->positiveId($id),
                $this->campaignId(),
                $this->jsonPayload()
            );
        });
    }

    /** DELETE /api/characters/{id}?campaignId=1 */
    public function delete($id = null)
    {
        return $this->execute(function () use ($id): array {
            $characterId = $this->positiveId($id);
            $this->characters->delete($this->auth(), $characterId, $this->campaignId());
            return ['id' => $characterId, 'message' => 'Character was deleted.'];
        });
    }

    /** POST /api/characters/{id}/purchase */
    public function purchase($id = null)
    {
        return $this->execute(function () use ($id): array {
            $characterId = $this->positiveId($id);
            $this->characters->assertEditable($this->auth(), $characterId, $this->campaignId());
            $input = $this->jsonPayload();
            $definitionId = filter_var(
                $input['definitionId'] ?? $input['definition_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($definitionId === false) {
                throw new CharacterException(
                    'validation_failed',
                    'Definition id must be a positive integer.',
                    422,
                    ['definitionId' => 'A valid definition is required.']
                );
            }
            return $this->development->purchaseDefinition($characterId, (int) $definitionId);
        });
    }

    /** GET /api/characters/{id}/assets */
    public function assets($id = null)
    {
        return $this->execute(function () use ($id): array {
            $characterId = $this->positiveId($id);
            $this->characters->show($this->auth(), $characterId, $this->campaignId());
            $payload = $this->assets->assetsForCharacter($characterId);
            if ($payload === null) {
                throw new CharacterException('character_not_found', 'Character was not found.', 404);
            }
            return $payload;
        });
    }

    /** GET /api/character-asset-sets/available */
    public function availableAssetSets()
    {
        return $this->execute(function (): array {
            $this->characters->assertAssetSetManager($this->auth());
            $sets = $this->assets->availableSets();
            return ['count' => count($sets), 'items' => $sets];
        });
    }

    /** POST /api/character-asset-sets */
    public function createAssetSet()
    {
        return $this->execute(function (): array {
            $this->characters->assertAssetSetManager($this->auth());
            $input = $this->jsonPayload();
            try {
                $set = $this->assets->createAvailableSet(
                    (string) ($input['name'] ?? ''),
                    (array) ($input['publicIds'] ?? $input['public_ids'] ?? [])
                );
            } catch (\InvalidArgumentException $exception) {
                throw new CharacterException(
                    'validation_failed',
                    $exception->getMessage(),
                    422,
                    ['assetSet' => $exception->getMessage()]
                );
            }
            return ['assetSet' => $set];
        }, 201);
    }

    /** PUT /api/characters/{id}/asset-set */
    public function assignAssetSet($id = null)
    {
        return $this->execute(function () use ($id): array {
            $characterId = $this->positiveId($id);
            $this->characters->assertEditable($this->auth(), $characterId, $this->campaignId());
            $input = $this->jsonPayload();
            $assetSetId = filter_var(
                $input['assetSetId'] ?? $input['asset_set_id'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            if ($assetSetId === false) {
                throw new CharacterException('validation_failed', 'Asset set id is invalid.', 422);
            }
            $result = $this->assets->assignSetToCharacter($characterId, (int) $assetSetId);
            if (!$result['ok']) {
                throw new CharacterException(
                    (string) $result['code'],
                    'Asset set could not be assigned.',
                    (int) $result['status']
                );
            }
            return $result;
        });
    }

    private function auth(): array
    {
        return $this->authContext->resolveFromRequest($this->request);
    }

    private function characterFilters(): array
    {
        $filters = [];
        foreach ([
            'user_id' => 'userId',
            'system_id' => 'systemId',
        ] as $legacy => $canonical) {
            $legacyValue = $this->request->getGet($legacy);
            $canonicalValue = $this->request->getGet($canonical);
            if ($legacyValue === null && $canonicalValue === null) {
                continue;
            }
            $legacyId = $legacyValue === null
                ? null : $this->queryId($legacyValue, $legacy);
            $canonicalId = $canonicalValue === null
                ? null : $this->queryId($canonicalValue, $canonical);
            if ($legacyId !== null && $canonicalId !== null && $legacyId !== $canonicalId) {
                throw new CharacterException(
                    'validation_failed',
                    'Conflicting character filters were provided.',
                    422,
                    [$canonical => 'Conflicting aliases were provided.']
                );
            }
            $filters[$legacy] = $canonicalId ?? $legacyId;
        }
        return $filters;
    }

    private function queryId($value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CharacterException(
                'validation_failed',
                'Character filter is invalid.',
                422,
                [$field => 'A positive integer is required.']
            );
        }
        return (int) $id;
    }

    private function campaignId(bool $required = false): ?int
    {
        $raw = $this->request->getGet('campaignId') ?? $this->request->getGet('campaign_id');
        if (($raw === null || $raw === '') && !$required) {
            return null;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new CharacterException('campaign_required', 'A valid campaign id is required.', 422);
        }
        return (int) $value;
    }

    private function positiveId($id): int
    {
        $value = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($value === false) {
            throw new CharacterException('character_not_found', 'Character was not found.', 404);
        }
        return (int) $value;
    }

    private function jsonPayload(): array
    {
        try {
            $payload = $this->request->getJSON(true);
        } catch (\Throwable $exception) {
            throw new CharacterException('invalid_json', 'Request body must contain valid JSON.', 400);
        }
        if (!is_array($payload)) {
            throw new CharacterException('invalid_json', 'Request body must be a JSON object.', 400);
        }
        return $payload;
    }

    private function execute(callable $operation, int $successStatus = 200)
    {
        try {
            return $this->respond($operation(), $successStatus);
        } catch (CharacterException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->errors()) {
                $payload['errors'] = $exception->errors();
            }
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
