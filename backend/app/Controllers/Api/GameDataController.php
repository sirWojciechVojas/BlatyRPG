<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\GameDefinitionModel;
use App\Models\RpgSystemModel;

class GameDataController extends ResourceController
{
    use ResponseTrait;

    protected $definitionModel;
    protected $systemModel;

    public function __construct()
    {
        $this->definitionModel = new GameDefinitionModel();
        $this->systemModel = new RpgSystemModel();
    }
     /**
     * GET /api/definitions/{id}
     * Zwraca pojedynczą definicję (umiejętność, czar, itp.) po ID.
     */
    public function show($id = null)
    {
        $data = $this->definitionModel->find($id);

        if (!$data) {
            return $this->failNotFound('Nie znaleziono definicji o podanym ID.');
        }

        return $this->respond($data);
    }

    /**
     * GET /api/systems/{id}/categories
     * Zwraca listę dostępnych kategorii (np. 'umiejetnosc', 'czar') dla danego systemu.
     */
    public function getCategories($systemId = null)
    {
        // Opcjonalnie: Walidacja czy system istnieje
        if (!$this->systemModel->find($systemId)) {
            return $this->failNotFound('System nie istnieje.');
        }

        // Pobieramy unikalne wartości z kolumny 'category' dla danego systemu
        $categories = $this->definitionModel
            ->select('category')
            ->distinct()
            ->where('system_id', $systemId)
            ->findAll();

        $result = array_column($categories, 'category');

        return $this->respond($result);
    }

    /**
     * GET /api/systems/{id}/data?category=xyz
     * Zwraca definicje mechaniczne (umiejętności, zdolności itp.).
     * Obsługuje filtrowanie po 'category'.
     */
    public function getDefinitions($systemId = null)
    {
        $category = $this->request->getGet('category');
        
        $query = $this->definitionModel->where('system_id', $systemId);

        if (!empty($category)) {
            $query->where('category', $category);
        }

        $data = $query->findAll();

        return $this->respond([
            'system_id' => $systemId,
            'category_filter' => $category ?? 'all',
            'count'     => count($data),
            'items'     => $data
        ]);
    }
}