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
        $this->systemModel   = new RpgSystemModel();
        $this->universeModel = new RpgUniverseModel();
    }
    
    /**
     * GET /api/games
     * NOWOŚĆ: Zwraca listę wszystkich par System-Uniwersum (czyli "Gier").
     * Pokazuje status is_active.
     */
    public function listGames()
    {
        // Logika pobierania danych przeniesiona do Modelu (Clean Code)
        $data = $this->systemModel->getGamesWithDetails();
        
        return $this->respond([
            'count' => count($data),
            'games' => $data
        ]);
    }

    /**
     * GET /api/systems
     */
    public function index()
    {
        $data = $this->systemModel->findAll();
        return $this->respond($data);
    }

    /**
     * GET /api/systems/{id}
     */
    public function show($id = null)
    {
        $data = $this->systemModel->find($id);
        
        if (!$data) {
            return $this->failNotFound('System RPG o podanym ID nie został znaleziony.');
        }

        return $this->respond($data);
    }

    /**
     * GET /api/universes/{id}
     * Zwraca szczegóły pojedynczego uniwersum po jego ID.
     */
    public function showUniverse($id = null)
    {
        $data = $this->universeModel->find($id);

        if (!$data) {
            return $this->failNotFound('Uniwersum o podanym ID nie zostało znalezione.');
        }

        return $this->respond($data);
    }

    /**
     * GET /api/systems/{id}/universes
     */
    public function systemUniverses($systemId = null)
    {
        if (!$this->systemModel->find($systemId)) {
            return $this->failNotFound('System o podanym ID nie istnieje.');
        }

        $universes = $this->universeModel
            ->select('rpg_universes.*')
            ->join('rpg_system_universes', 'rpg_system_universes.universe_id = rpg_universes.id')
            ->where('rpg_system_universes.system_id', $systemId)
            ->findAll();
        
        return $this->respond($universes);
    }
}