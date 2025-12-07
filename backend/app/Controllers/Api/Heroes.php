<?php

namespace App\Controllers\Api;

use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\RESTful\ResourceController;

class Heroes extends ResourceController
{
    protected $modelName = 'App\Models\HeroModel';
    protected $format = 'json';

    /**
     * Return an array of resource objects, themselves in array format.
     *
     * @return ResponseInterface
     */
    public function index()
    {
        $heroes = $this->model->findAll();

        return $this->respond($heroes);
    }

    /**
     * Return the properties of a resource object.
     *
     * @param int|string|null $id
     *
     * @return ResponseInterface
     */
    public function show($id = null)
    {
        $hero = $this->model->find($id);

        if(!$hero){
            return $this->failNotFound('Nie znaleziono bohatera o ID: '. $id);
        }

        return $this->respond($hero);
    }

     /**
     * Create a new resource object, from "posted" parameters.
     *
     * @return ResponseInterface
     */
    public function create()
    {
        // Pobieramy dane z requestu JSON
        $data = $this->request->getJSON(true); // true = jako tablica asocjacyjna

        // Próba zapisu (Model uruchomi walidację zdefiniowaną w HeroModel)
        if (!$this->model->insert($data)) {
            // Jeśli walidacja modelu nie przejdzie, zwracamy błąd 400 z listą błędów
            return $this->failValidationErrors($this->model->errors());
        }

        // Zwracamy status 201 Created i dane nowej postaci
        $hero = $this->model->find($this->model->getInsertID());
        return $this->respondCreated($hero, 'Bohater został stworzony pomyślnie.');
    }

    /**
     * Add or update a model resource, from "posted" properties.
     *
     * @param int|string|null $id
     *
     * @return ResponseInterface
     */
    public function update($id = null)
    {
        // Sprawdzamy czy istnieje
        if (!$this->model->find($id)) {
            return $this->failNotFound('Nie można edytować. Brak ID: ' . $id);
        }

        $data = $this->request->getJSON(true);

        if (!$this->model->update($id, $data)) {
            return $this->failValidationErrors($this->model->errors());
        }

        return $this->respond($this->model->find($id), 200, 'Zaktualizowano.');
    }

    /**
     * Delete the designated resource object from the model.
     *
     * @param int|string|null $id
     *
     * @return ResponseInterface
     */
    public function delete($id = null)
    {
        if (!$this->model->find($id)) {
            return $this->failNotFound('Nie można usunąć. Brak ID: ' . $id);
        }

        $this->model->delete($id);

        return $this->respondDeleted(['id' => $id], 'Bohater usunięty.');
    }
}
