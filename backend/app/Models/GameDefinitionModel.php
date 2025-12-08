<?php

namespace App\Models;

use CodeIgniter\Model;

class GameDefinitionModel extends Model
{
    protected $table            = 'game_definitions';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['system_id', 'category', 'name', 'description', 'metadata'];

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
    protected $afterFind      = ['decodeMetadata'];
    protected $beforeDelete   = [];
    protected $afterDelete    = [];

    protected function decodeMetadata(array $data)
    {
        // Sprawdzamy, czy mamy dane do przetworzenia
        if (!isset($data['data'])) {
            return $data;
        }

        // Przypadek 1: Pobranie wielu rekordów (findAll) -> tablica tablic
        if ($data['singleton'] === false) {
            foreach ($data['data'] as &$row) {
                if (isset($row['metadata']) && is_string($row['metadata'])) {
                    $decoded = json_decode($row['metadata'], true);
                    // Jeśli dekodowanie się udało, podmieniamy string na tablicę
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $row['metadata'] = $decoded;
                    }
                }
            }
        }
        // Przypadek 2: Pobranie jednego rekordu (find) -> pojedyncza tablica
        else {
            if (isset($data['data']['metadata']) && is_string($data['data']['metadata'])) {
                $decoded = json_decode($data['data']['metadata'], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $data['data']['metadata'] = $decoded;
                }
            }
        }

        return $data;
    }
}