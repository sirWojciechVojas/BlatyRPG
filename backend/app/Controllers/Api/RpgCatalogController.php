<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\API\ResponseTrait;
use App\Models\RpgSystemModel;
use App\Models\RpgUniverseModel;

class RpgCatalogController extends ResourceController
{
    use ResponseTrait;

    protected $systemModel;
    protected $universeModel;

    public function __construct()
    {
        $this->systemModel = new RpgSystemModel();
        $this->universeModel = new RpgUniverseModel();
    }

    // GET /api/systems
    public function index()
    {
        $data = $this->systemModel->findAll();
        return $this->respond($data);
    }

    // GET /api/systems/(:num)
    public function show($id = null)
    {
        $data = $this->systemModel->find($id);
        
        if (!$data) {
            return $this->failNotFound('System RPG o podanym ID nie został znaleziony.');
        }

        return $this->respond($data);
    }

    // GET /api/systems/(:num)/universes
    public function systemUniverses($systemId = null)
    {
        // Sprawdź czy system istnieje
        if (!$this->systemModel->find($systemId)) {
            return $this->failNotFound('System nie istnieje.');
        }

        // Pobierz uniwersa gdzie default_system_id = systemId
        $universes = $this->universeModel->where('default_system_id', $systemId)->findAll();
        
        return $this->respond($universes);
    }
}