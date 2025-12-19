<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CharacterModel;
use App\Services\CharacterService;
use Exception;

class CharacterController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = CharacterModel::class;
    protected $format    = 'json';
    protected $characterService;

    public function __construct()
    {
        // Stworzenie instancji serwisu postaci
        $this->characterService = new CharacterService();
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

        return $this->respond($character);
    }

    /**
     * POST /api/characters
     * Tworzenie nowej postaci
     * Body: { "name": "Geralt", "system_id": 1, "universe_id": 1, "data": { ... } }
     */
    public function create()
    {
        $input = $this->request->getJSON(true); // true = jako tablica asocjacyjna

        // Tutaj można dodać logikę pobierania ID zalogowanego usera
        // np. $input['user_id'] = auth()->id();
        // Na razie zakładamy, że przychodzi w requeście lub jest NULL

        if (!$this->model->insert($input)) {
            return $this->failValidationErrors($this->model->errors());
        }

        $newId = $this->model->getInsertID();
        $character = $this->model->find($newId);

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
        $input = $this->request->getJSON(true);

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

        if ($this->model->delete($id)) {
            return $this->respondDeleted(['message' => "Postać o ID $id została usunięta."]);
        }

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
}