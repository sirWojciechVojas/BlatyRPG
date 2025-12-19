<?php

namespace App\Libraries\GameStrategies;

use Exception;

class Wfrp2edStrategy implements GameSystemStrategyInterface
{
    public function canPurchase(array $characterData, array $definition): void
    {
        // Tu można dodać walidację profesji (np. czy talent jest w schemacie rozwoju)
    }

    public function applyPurchase(array &$characterData, array $definition): array
    {
        // Upewniamy się, że klucze istnieją
        if (!isset($characterData['attributes'])) $characterData['attributes'] = [];
        $attr = &$characterData['attributes'];
        
        $name = $definition['name'];
        $meta = $definition['metadata'] ?? []; 
        $cat  = $definition['category'];

        $updates = [];

        // --- A. UMIEJĘTNOŚCI (0 -> +10 -> +20) ---
        if ($cat === 'umiejetnosc') {
            if (!isset($attr['skills'])) $attr['skills'] = [];
            
            $base    = $name;
            $plus10  = $name . ' +10';
            $plus20  = $name . ' +20';

            if (in_array($plus20, $attr['skills'])) {
                throw new Exception("Osiągnięto maksymalny poziom umiejętności ($name).");
            } elseif (in_array($plus10, $attr['skills'])) {
                $key = array_search($plus10, $attr['skills']);
                $attr['skills'][$key] = $plus20;
                $updates['skill_updated'] = $plus20;
            } elseif (in_array($base, $attr['skills'])) {
                $key = array_search($base, $attr['skills']);
                $attr['skills'][$key] = $plus10;
                $updates['skill_updated'] = $plus10;
            } else {
                $attr['skills'][] = $base;
                $updates['skill_added'] = $base;
            }
        }
        // --- B. ZDOLNOŚCI (Talents) ---
        elseif ($cat === 'zdolnosc') {
            if (!isset($attr['talents'])) $attr['talents'] = [];

            if (in_array($name, $attr['talents'])) {
                throw new Exception("Postać posiada już zdolność: $name");
            }

            $attr['talents'][] = $name;
            $updates['talent_added'] = $name;

            // BONUSY DO STATYSTYK (np. Bardzo Silny +5 S)
            // Mapa ID cechy w seederze -> Klucz w JSON "actual"
            // 0=ww, 1=us, 2=k, 3=odp, 4=zr, 5=int, 6=sw, 7=ogd, 8=a, 9=zyw, 10=s, 11=wt, 12=sz, 13=mag
            $statMap = [
                0 => 'ww', 1 => 'us', 2 => 'k', 3 => 'odp', 4 => 'zr', 
                5 => 'int', 6 => 'sw', 7 => 'ogd', 8 => 'a', 9 => 'zyw', 
                10 => 's', 11 => 'wt', 12 => 'sz', 13 => 'mag'
            ];

            if (isset($meta['modyfikator']) && isset($meta['talent']) && is_numeric($meta['talent'])) {
                $statIndex = (int)$meta['talent'];
                $bonus     = (int)$meta['modyfikator'];

                if (isset($statMap[$statIndex])) {
                    $statKey = $statMap[$statIndex];
                    
                    // Inicjalizacja tablicy actual jeśli brak
                    if (!isset($attr['actual'])) $attr['actual'] = [];
                    if (!isset($attr['actual'][$statKey])) $attr['actual'][$statKey] = 0;

                    $attr['actual'][$statKey] += $bonus;
                    $updates['stat_bonus'] = "$statKey +$bonus";
                }
            }
        }

        return $updates;
    }
}