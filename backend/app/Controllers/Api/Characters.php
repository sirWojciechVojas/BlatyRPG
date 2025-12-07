<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Characters extends ResourceController
{
    protected $modelName = 'App\Models\CharacterModel';
    protected $format    = 'json';

    /**
     * GET /api/characters
     * Zwraca postacie. W przyszłości dodamy tu filtrowanie:
     * - tylko postacie zalogowanego usera
     * - tylko postacie z konkretnej kampanii
     */
    public function index()
    {
        // Na razie zwracamy wszystko (dla testów w Postmanie)
        $data = $this->model->findAll();
        return $this->respond($data);
    }

    /**
     * GET /api/characters/{id}
     */
    public function show($id = null)
    {
        $character = $this->model->find($id);
        if (!$character) {
            return $this->failNotFound('Postać nie znaleziona: ' . $id);
        }
        
        // Dekodowanie JSON-ów, jeśli baza zwraca stringi (zależy od sterownika bazy)
        // Helper function do czyszczenia danych
        $character = $this->decodeJsonFields($character);

        return $this->respond($character);
    }

    /**
     * POST /api/characters
     */
    public function create()
    {
        $data = $this->request->getJSON(true);
        
        // TODO: Tutaj w przyszłości pobierzemy ID zalogowanego usera
        // $data['user_id'] = auth()->id(); 
        // Na razie hardcodujemy usera nr 1 (musisz go stworzyć w bazie!)
        if (!isset($data['user_id'])) {
            $data['user_id'] = 1; 
        }

        if (!$this->model->insert($data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respondCreated($this->model->find($this->model->getInsertID()));
    }

    /**
     * PUT /api/characters/{id}
     */
    public function update($id = null)
    {
        if (!$this->model->find($id)) {
            return $this->failNotFound();
        }

        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond($this->model->find($id));
    }

    /**
     * DELETE /api/characters/{id}
     */
    public function delete($id = null)
    {
        if (!$this->model->find($id)) {
            return $this->failNotFound();
        }
        $this->model->delete($id);
        return $this->respondDeleted(['id' => $id]);
    }

    // Pomocnicza metoda do dekodowania JSON z bazy (jeśli Driver tego nie robi sam)
    private function decodeJsonFields(array $char): array
    {
        $jsonFields = ['stats_json', 'skills_json', 'talents_json', 'spells_json'];
        foreach ($jsonFields as $field) {
            if (isset($char[$field]) && is_string($char[$field])) {
                $char[$field] = json_decode($char[$field], true);
            }
        }
        return $char;
    }
}