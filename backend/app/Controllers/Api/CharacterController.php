<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\CharacterModel;

class CharacterController extends ResourceController
{
    use ResponseTrait;

    protected $modelName = CharacterModel::class;
    protected $format    = 'json';

    /**
     * GET /api/characters
     * Zwraca listę postaci.
     * Opcjonalnie filtruje po user_id (np. ?user_id=1)
     */
    public function index()
    {
        // Pobierz parametry filtrowania z URL
        $userId   = $this->request->getGet('user_id');
        $systemId = $this->request->getGet('system_id');

        $query = $this->model;

        if ($userId) {
            $query = $query->where('user_id', $userId);
        }
        
        if ($systemId) {
            $query = $query->where('system_id', $systemId);
        }

        // Sortowanie: najnowsze na górze
        $data = $query->orderBy('created_at', 'DESC')->findAll();

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
}