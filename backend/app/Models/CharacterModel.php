<?php

namespace App\Models;

use CodeIgniter\Model;

class CharacterModel extends Model
{
    protected $table            = 'characters';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    
    protected $allowedFields    = [
        'user_id', 
        'campaign_id', 
        'system_id', 
        'universe_id', 
        'name', 
        'data',
        'avatar_url',
        'avatar',
        'brass',
        'primary_currency_code'
    ];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    // Walidacja
    protected $validationRules = [
        'name'        => 'required|min_length[2]|max_length[150]',
        'system_id'   => 'required|integer',
        'universe_id' => 'required|integer',
    ];

    // --- CALLBACKI (Magia JSON) ---
    
    // 1. ODCZYT: Po pobraniu z bazy, zamień string JSON na tablicę PHP
    protected $afterFind = ['decodeJsonData'];

    // 2. ZAPIS: Przed zapisem do bazy, upewnij się, że dane są spakowane do stringa (jeśli nie robi tego kontroler)
    protected $beforeInsert = ['assignPrimaryCurrency', 'encodeJsonData'];
    protected $beforeUpdate = ['encodeJsonData'];

    /**
     * Odczyt: Rozpakuj JSON (String -> Array)
     * Dzięki temu API zwróci ładny obiekt, a nie napis z ukośnikami.
     */
    protected function decodeJsonData(array $data)
    {
        // Jeśli nie ma danych lub wynik jest pusty, nic nie rób
        if (!isset($data['data'])) return $data;

        // Funkcja pomocnicza do dekodowania pojedynczego wiersza
        $decodeRow = function(&$row) {
            // Sprawdzamy czy pole 'data' istnieje i czy jest napisem
            if (isset($row['data']) && is_string($row['data'])) {
                $decoded = json_decode($row['data'], true); // true = tablica asocjacyjna
                
                // Jeśli dekodowanie się uda, podmieniamy string na tablicę
                if (json_last_error() === JSON_ERROR_NONE) {
                    $row['data'] = $decoded;
                }
            }
        };

        // Obsługa findAll() - wielowymiarowa tablica wyników
        if ($data['singleton'] === false) {
            foreach ($data['data'] as &$row) {
                $decodeRow($row);
            }
        } 
        // Obsługa find() - pojedynczy wynik
        else {
            $decodeRow($data['data']);
        }

        return $data;
    }

    /**
     * Zapis: Spakuj tablicę do JSON (Array -> String)
     */
    protected function encodeJsonData(array $data)
    {
        // Sprawdzamy czy w danych do zapisu (w insert/update) jest klucz 'data'
        if (isset($data['data']['data'])) {
            $incomingData = $data['data']['data'];
            
            // Jeśli to tablica lub obiekt, zamieniamy na string JSON
            if (is_array($incomingData) || is_object($incomingData)) {
                $data['data']['data'] = json_encode($incomingData, JSON_UNESCAPED_UNICODE);
            }
        }
        return $data;
    }

    protected function assignPrimaryCurrency(array $data)
    {
        if (!empty($data['data']['primary_currency_code'])) {
            return $data;
        }
        $systemId = (int) ($data['data']['system_id'] ?? 0);
        $system = $systemId > 0
            ? $this->db->table('rpg_systems')->select('code')->where('id', $systemId)->get()->getRowArray()
            : null;
        $currencies = (new \App\Services\Shop\ShopCurrencyService())
            ->definitionsForSystem((string) ($system['code'] ?? 'generic'));
        $data['data']['primary_currency_code'] = (string) ($currencies[0]['code'] ?? 'generic');
        return $data;
    }
    /**
     * Zakres filtrowania (Scope)
     * Pozwala łatwo filtrować wyniki w Kontrolerze.
     * Użycie: $model->filterBy(['user_id' => 1])->findAll();
     */
    public function filterBy(array $params)
    {
        $builder = $this->builder(); // Pobieramy buildera, żeby nie resetować stanu modelu

        if (!empty($params['user_id'])) {
            $this->where('user_id', $params['user_id']);
        }

        if (!empty($params['system_id'])) {
            $this->where('system_id', $params['system_id']);
        }
        
        // Można tu łatwo dodać więcej filtrów w przyszłości, np. universe_id

        return $this; // Zwracamy instancję modelu dla łańcuchowania metod (method chaining)
    }
}
