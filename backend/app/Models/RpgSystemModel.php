<?php

namespace App\Models;

use CodeIgniter\Model;

class RpgSystemModel extends Model
{
    protected $table            = 'rpg_systems';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['code', 'name', 'description'];

    protected bool $allowEmptyInserts = false;

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

    // Validation
    protected $validationRules      = [];
    protected $validationMessages   = [];
    protected $skipValidation       = false;
    protected $cleanValidationRules = true;

    // Callbacks
    protected $allowCallbacks = true;
    protected $beforeInsert   = [];
    protected $afterInsert    = [];
    protected $beforeUpdate   = [];
    protected $afterUpdate    = [];
    protected $beforeFind     = [];
    protected $afterFind      = [];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    /**
     * Zwraca listę wszystkich gier (System + Uniwersum) z tabeli łączącej.
     * Przeniesione z kontrolera RpgCatalogController.
     */
    public function getGamesWithDetails()
    {
        $db = \Config\Database::connect();
        $builder = $db->table('rpg_system_universes');

        $builder->select('
            rpg_system_universes.system_id,
            rpg_systems.name as system_name,
            rpg_systems.code as system_code,
            rpg_system_universes.universe_id,
            rpg_universes.name as universe_name,
            rpg_universes.code as universe_code,
            rpg_system_universes.is_active
        ');
        $builder->join('rpg_systems', 'rpg_systems.id = rpg_system_universes.system_id');
        $builder->join('rpg_universes', 'rpg_universes.id = rpg_system_universes.universe_id');
        
        $builder->orderBy('rpg_systems.name', 'ASC');

        return $builder->get()->getResultArray();
    }
}
