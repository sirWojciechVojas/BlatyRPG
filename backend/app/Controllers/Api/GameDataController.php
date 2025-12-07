<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\GameDefinitionModel;

class GameDataController extends ResourceController
{
    use ResponseTrait;

    protected $defModel;

    public function __construct()
    {
        $this->defModel = new GameDefinitionModel();
    }

    // GET /api/systems/(:num)/data?category=umiejetnosc
    public function getDefinitions($systemId = null)
    {
        $category = $this->request->getGet('category');
        
        $query = $this->defModel->where('system_id', $systemId);

        if ($category) {
            $query->where('category', $category);
        }

        $data = $query->findAll();

        return $this->respond([
            'system_id' => $systemId,
            'count'     => count($data),
            'data'      => $data
        ]);
    }

    // GET /api/systems/(:num)/categories
    public function getCategories($systemId = null)
    {
        // Pobiera unikalne kategorie dostępne dla danego systemu
        $categories = $this->defModel
            ->select('category')
            ->distinct()
            ->where('system_id', $systemId)
            ->findAll();

        // Spłaszczamy tablicę, żeby zwrócić prostą listę stringów: ['umiejetnosc', 'zdolnosc']
        $result = array_column($categories, 'category');

        return $this->respond($result);
    }
}