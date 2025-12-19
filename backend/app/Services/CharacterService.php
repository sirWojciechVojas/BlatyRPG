<?php

namespace App\Services;

use App\Models\CharacterModel;
use App\Models\GameDefinitionModel;
use App\Models\RpgSystemModel;
use App\Libraries\GameStrategies\GameStrategyFactory;
use Exception;

class CharacterService
{
    protected $charModel;
    protected $defModel;
    protected $sysModel;
    protected $db;

    public function __construct()
    {
        $this->charModel = new CharacterModel();
        $this->defModel  = new GameDefinitionModel();
        $this->sysModel  = new RpgSystemModel();
        $this->db        = \Config\Database::connect();
    }

    /**
     * Główna metoda biznesowa zakupu elementu (umiejętności/zdolności).
     * @param int $charId ID Postaci
     * @param int $defId ID Definicji z game_definitions
     */
    public function purchaseDefinition(int $charId, int $defId): array
    {
        // 1. Pobierz dane
        $character = $this->charModel->find($charId);
        $definition = $this->defModel->find($defId);

        if (!$character) throw new Exception("Postać o ID $charId nie istnieje.", 404);
        if (!$definition) throw new Exception("Definicja o ID $defId nie istnieje.", 404);

        if ($character['system_id'] != $definition['system_id']) {
            throw new Exception("Niezgodność systemów gry. Nie możesz kupić elementu z innego systemu.", 409);
        }

        // 2. Pobierz kod systemu (np. 'wfrp2ed') z bazy
        $system = $this->sysModel->find($character['system_id']);
        $sysCode = $system ? $system['code'] : 'unknown';

        // 3. Sprawdź Koszt XP
        // Metadata jest już tablicą dzięki Modelowi
        $meta = $definition['metadata'] ?? [];
        $cost = isset($meta['koszt_xp']) ? (int)$meta['koszt_xp'] : 100; // Domyślny koszt
        
        // Pobieramy dane JSON (jako tablicę PHP)
        $charData = $character['data']; 
        
        // Bezpieczne pobieranie obecnego XP
        $currentExp = 0;
        if (isset($charData['experience']['current'])) {
            $currentExp = (int)$charData['experience']['current'];
        } elseif (isset($charData['attributes']['exp']['current'])) {
            // Wsparcie dla starszej struktury z seedera (jeśli tam wpadło)
            $currentExp = (int)$charData['attributes']['exp']['current'];
        }

        if ($currentExp < $cost) {
            throw new Exception("Brak wystarczającej ilości PD. Wymagane: $cost, Posiadane: $currentExp", 402);
        }

        // 4. Pobierz odpowiednią Strategię i wykonaj logikę
        $strategy = GameStrategyFactory::getStrategy($sysCode);
        
        // a) Walidacja (czy można kupić?)
        $strategy->canPurchase($charData, $definition);
        
        // b) Aplikacja zmian (modyfikuje $charData przez referencję)
        $updates = $strategy->applyPurchase($charData, $definition);

        // 5. Odejmij XP i Zapisz
        if (isset($charData['experience']['current'])) {
            $charData['experience']['current'] = $currentExp - $cost;
        } elseif (isset($charData['attributes']['exp']['current'])) {
             $charData['attributes']['exp']['current'] = $currentExp - $cost;
        } else {
             // Jeśli struktura exp nie istnieje, tworzymy ją
             $charData['experience'] = ['current' => 0 - $cost, 'total' => 0];
        }
        
        // Aktualizacja w bazie
        $this->charModel->update($charId, ['data' => $charData]);

        return [
            'message' => "Zakupiono pomyślnie: {$definition['name']}",
            'xp_cost' => $cost,
            'remaining_xp' => $currentExp - $cost,
            'updates' => $updates
        ];
    }
}