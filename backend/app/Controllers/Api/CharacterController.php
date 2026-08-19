<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CharacterModel;
use App\Services\CharacterAssetService;
use App\Services\CharacterService;
use App\Services\Shop\AuthContextService;
use Exception;

class CharacterController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = CharacterModel::class;
    protected $format    = 'json';
    protected $characterService;
    protected $characterAssetService;
    protected $authContextService;

    public function __construct()
    {
        // Stworzenie instancji serwisu postaci
        $this->characterService = new CharacterService();
        $this->characterAssetService = new CharacterAssetService();
        $this->authContextService = new AuthContextService();
    }
    
    /**
     * GET /api/characters
     * Zwraca listę postaci.
     * Opcjonalnie filtruje po user_id (np. ?user_id=1)
     */
    public function index()
    {
        // Pobierz parametry filtrowania z URL
        $filters = [
            'user_id'   => $this->request->getGet('user_id'),
            'system_id' => $this->request->getGet('system_id'),
        ];

        // Użycie Scope z Modelu (Clean Code)
        $data = $this->model
            ->filterBy($filters)
            ->orderBy('created_at', 'DESC')
            ->findAll();
        $data = $this->characterAssetService->hydrateCharacters($data);

        return $this->respond([
            'count' => count($data),
            'items' => $data
        ]);
    }

    /**
     * GET /api/characters/{id}
     * Szczegóły postaci
     */
    public function show($id = null)
    {
        $character = $this->model->find($id);

        if (!$character) {
            return $this->failNotFound("Postać o ID $id nie została znaleziona.");
        }

        return $this->respond($this->characterAssetService->hydrateCharacters([$character])[0]);
    }

    /**
     * POST /api/characters
     * Tworzenie nowej postaci
     * Body: { "name": "Geralt", "system_id": 1, "universe_id": 1, "data": { ... } }
     */
    public function create()
    {
        $input = (array) $this->request->getJSON(true); // true = jako tablica asocjacyjna
        $assetSetId = (int) ($input['asset_set_id'] ?? $input['assetSetId'] ?? 0);
        unset($input['asset_set_id'], $input['assetSetId']);

        $auth = $this->authContextService->resolveFromRequest($this->request);
        if (!$this->authContextService->isGmOrAdmin($auth) && !empty($auth['user_id'])) {
            $input['user_id'] = (int) $auth['user_id'];
        }

        // Tutaj można dodać logikę pobierania ID zalogowanego usera
        // np. $input['user_id'] = auth()->id();
        // Na razie zakładamy, że przychodzi w requeście lub jest NULL

        $db = \Config\Database::connect();
        $db->transBegin();
        if (!$this->model->insert($input)) {
            $db->transRollback();
            return $this->failValidationErrors($this->model->errors());
        }

        $newId = $this->model->getInsertID();
        if ($assetSetId > 0) {
            $assignment = $this->characterAssetService->assignSetToCharacter(
                (int) $newId,
                $assetSetId,
                false
            );
            if (!$assignment['ok']) {
                $db->transRollback();
                return $this->fail(
                    ['code' => $assignment['code']],
                    (int) $assignment['status']
                );
            }
        }
        if ($db->transStatus() === false) {
            $db->transRollback();
            return $this->failServerError('Nie udało się utworzyć postaci.');
        }
        $db->transCommit();
        $character = $this->characterAssetService->hydrateCharacters([
            $this->model->find($newId),
        ])[0];

        return $this->respondCreated([
            'message' => 'Postać została utworzona.',
            'character' => $character
        ]);
    }

    /**
     * PUT/PATCH /api/characters/{id}
     * Edycja postaci
     */
    public function update($id = null)
    {
        $input = (array) $this->request->getJSON(true);
        // Assignment is intentionally handled by the dedicated endpoint so the
        // set status and one-character invariant always change atomically.
        unset($input['asset_set_id'], $input['assetSetId']);

        // Sprawdź czy postać istnieje
        if (!$this->model->find($id)) {
            return $this->failNotFound("Postać o ID $id nie istnieje.");
        }

        if (!$this->model->update($id, $input)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond([
            'message' => 'Postać zaktualizowana pomyślnie.',
            'id' => $id
        ]);
    }

    /**
     * DELETE /api/characters/{id}
     * Usuwanie postaci
     */
    public function delete($id = null)
    {
        if (!$this->model->find($id)) {
            return $this->failNotFound("Postać o ID $id nie istnieje.");
        }

        $db = \Config\Database::connect();
        $db->transBegin();
        $this->characterAssetService->releaseCharacterSet((int) $id, false);
        if ($this->model->delete($id)) {
            $db->transCommit();
            return $this->respondDeleted(['message' => "Postać o ID $id została usunięta."]);
        }

        $db->transRollback();

        return $this->failServerError('Nie udało się usunąć postaci.');
    }

    /**
     * POST /api/characters/{id}/purchase
     * Specjalna metoda do kupowania umiejętności i zdolności z walidacją zasad.
     * Body: { "definition_id": 55 }
     */
    public function purchase($id = null)
    {
        $input = $this->request->getJSON(true);
        $defId = $input['definition_id'] ?? null;

        if(!$defId) {
            return $this->fail('Wymagane pole definition_id.', 400);
        }

        try {
            // Przekazanie sterowania do Serwisu (Logika Biznesowa)
            $result = $this->characterService->purchaseDefinition($id, $defId);

            return $this->respond($result);
        } catch (Exception $e) {
            // Obsługa błędów biznesowych (brak XP, zła walidacja itp.)
            $code = $e->getCode();
            // Upewnienie czy kod HTTP jest poprawny (400-599), inaczej 400
            $httpCode = ($code >= 400 && $code < 600) ? $code : 400;

            return $this->fail($e->getMessage(), $httpCode);
        }
    }

    /** GET /api/characters/{id}/assets */
    public function assets($id = null)
    {
        $payload = $this->characterAssetService->assetsForCharacter((int) $id);
        if ($payload === null) {
            return $this->failNotFound('Postać nie została znaleziona.');
        }
        return $this->respond($payload);
    }

    /** GET /api/character-asset-sets/available */
    public function availableAssetSets()
    {
        $sets = $this->characterAssetService->availableSets();
        return $this->respond(['count' => count($sets), 'items' => $sets]);
    }

    /** POST /api/character-asset-sets (GM/admin preparation endpoint). */
    public function createAssetSet()
    {
        $auth = $this->authContextService->resolveFromRequest($this->request);
        if (!$this->authContextService->isGmOrAdmin($auth)) {
            return $this->failForbidden('Tylko GM może przygotowywać zestawy grafik.');
        }
        $input = (array) $this->request->getJSON(true);
        try {
            $set = $this->characterAssetService->createAvailableSet(
                (string) ($input['name'] ?? ''),
                (array) ($input['publicIds'] ?? $input['public_ids'] ?? [])
            );
            return $this->respondCreated(['assetSet' => $set]);
        } catch (\InvalidArgumentException $error) {
            return $this->failValidationErrors(['assetSet' => $error->getMessage()]);
        }
    }

    /** PUT /api/characters/{id}/asset-set */
    public function assignAssetSet($id = null)
    {
        $character = $this->model->find($id);
        if (!$character) {
            return $this->failNotFound('Postać nie została znaleziona.');
        }
        if (!$this->canManageCharacter($character)) {
            return $this->failForbidden('Nie masz uprawnień do zmiany grafik tej postaci.');
        }
        $input = (array) $this->request->getJSON(true);
        $assetSetId = (int) ($input['assetSetId'] ?? $input['asset_set_id'] ?? 0);
        $result = $this->characterAssetService->assignSetToCharacter((int) $id, $assetSetId);
        if (!$result['ok']) {
            return $this->fail(['code' => $result['code']], (int) $result['status']);
        }
        return $this->respond($result);
    }

    private function canManageCharacter(array $character): bool
    {
        $auth = $this->authContextService->resolveFromRequest($this->request);
        if ($this->authContextService->isGmOrAdmin($auth)) {
            return true;
        }
        return !empty($auth['user_id'])
            && (int) ($character['user_id'] ?? 0) === (int) $auth['user_id'];
    }
}
