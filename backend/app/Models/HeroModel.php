<?php

namespace App\Models;

use CodeIgniter\Model;

class HeroModel extends Model
{
    // 1. Konfiguracja Tabeli
    protected $table            = 'heroes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;

    // 2. Format danych
    // 'array' jest idealny dla API JSON. 'object' też jest ok.
    protected $returnType       = 'array';


    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = ['name','class','level'];

    protected bool $allowEmptyInserts = false;

    // 5. Automatyczne daty
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
    protected $deletedField  = 'deleted_at';

     // 6. Walidacja (Strażnik danych)
    // Model sprawdzi to ZANIM dane trafią do bazy.
    protected $validationRules      = [
        'name'  => 'required|min_length[2]|max_length[100]',
        'class' => 'required|min_length[2]|max_length[100]',
        'level' => 'required|integer|greater_than_equal_to[1]',    
    ];
    protected $validationMessages   = [
        'name' => [
            'required' => 'Imię bohatera jest wymagane.',
            'min_length' => 'Imię jest za krótkie.',
        ],
        'level' => [
            'integer' => 'Poziom musi być liczbą.',
        ]
    ];
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
}
