<?php

namespace App\Libraries;

class CharacterDevelopment
{
    /**
     * Zwraca pełny obraz rozwoju postaci:
     * 1. Co już ma (owned).
     * 2. Co może kupić (available) - uwzględniając limity profesji i odblokowania MG.
     * 3. Cechy (Attributes) - ile wykupiono vs limit profesji.
     */
    public function getProgressionTree(array $currentProfession, array $ownedSkills, array $attributeAdvances, array $customUnlocks)
    {
        return [
            'attributes' => $this->analyzeAttributes($currentProfession, $attributeAdvances),
            'skills'     => $this->analyzeSkills($currentProfession['available_skills'], $ownedSkills, $customUnlocks['skills'] ?? []),
            // 'talents' => analogicznie do skills...
        ];
    }

    /**
     * Analiza Cech (Attributes)
     * Sprawdza ile postać ma wykupione vs ile oferuje profesja.
     * Np. Profesja daje +20%, postać ma kupione +10%. Wynik: Można kupić jeszcze +10%.
     */
    private function analyzeAttributes(array $profession, array $advances)
    {
        $result = [];
        // Mapowanie nazw kolumn z BD na klucze czytelne dla frontu
        $map = [
            'weapon_skill' => 'WW', 'ballistic_skill' => 'US', 'strength' => 'K',
            'toughness' => 'Odp', 'agility' => 'Zr', 'intelligence' => 'Int',
            'willpower' => 'SW', 'fellowship' => 'Ogd', 'attacks' => 'A',
            'wounds' => 'Żyw', 'magic' => 'Mag'
        ];

        foreach ($map as $dbColumn => $label) {
            $maxScheme = (int)$profession[$dbColumn]; // Np. +20
            $currentBought = $advances[$dbColumn] ?? 0; // Np. +10

            // Logika "Sumowania":
            // Możesz kupić tylko tyle, ile wynosi schemat MINUS to co już masz wykupione.
            // Ale nie możesz kupić mniej niż 0 (jeśli zmieniłeś profesję na słabszą).
            $remaining = max(0, $maxScheme - $currentBought);

            // W WFRP kupuje się paczkami po 5% (cechy główne) lub 1 (cechy drugorzędne)
            $step = in_array($dbColumn, ['attacks', 'wounds', 'magic']) ? 1 : 5;
            $canBuy = ($remaining >= $step);

            $result[$dbColumn] = [
                'label' => $label,
                'total_scheme' => $maxScheme,        // Limit z obecnej profesji
                'current_advances' => $currentBought, // Ile już wydano PD w historii
                'remaining_to_buy' => $remaining,    // Ile jeszcze można kupić w tej profesji
                'can_buy_next_step' => $canBuy,      // Czy można kliknąć "Kup"
                'cost' => 100 // Koszt w PD (stały w WFRP 2ed)
            ];
        }

        return $result;
    }

    /**
     * Analiza Umiejętności (Skills)
     * Łączy pulę profesji i pulę MG, a potem odejmuje to co już posiadane.
     */
    private function analyzeSkills(string $professionString, array $ownedSkills, array $gmUnlocks)
    {
        $professionPool = $this->parseString($professionString);

        // Dodaj odblokowania MG do puli "dostępnych"
        // $gmUnlocks to tablica ID skillów, np. [102, 105]
        foreach ($gmUnlocks as $unlock) {
            $professionPool[] = [
                'id' => $unlock['skill_id'],
                'details' => $unlock['details'] ?? null,
                'source' => 'gm_unlock', // Oznaczenie dla frontendu (np. inny kolor)
                'is_choice' => false
            ];
        }

        $result = [];

        foreach ($professionPool as $item) {
            $status = $this->checkSkillStatus($item, $ownedSkills);

            // Logika sumowania poziomów umiejętności:
            // Jeśli masz poziom 0 (wykupiona), a profesja oferuje tę umiejętność,
            // to możesz kupić poziom 1 (+10%).
            // Jeśli profesja oferuje, a Ty nie masz, kupujesz poziom 0.

            if ($status['owned']) {
                $nextLevel = $status['current_level'] + 1;
                // W WFRP 2ed max to zazwyczaj +20% (level 2), chyba że są specjalne zasady.
                // Tutaj sprawdzamy: czy mogę kupić wyższy poziom?
                // Standardowo profesja daje dostęp do +0. Jeśli występuje w schemacie 2 razy (np. "Unik, Unik"),
                // to pozwala na +10. To wymagałoby bardziej skomplikowanego parsera zliczającego wystąpienia.
                // UPROSZCZENIE: Jeśli masz już skill, a jest on w schemacie AKTUALNEJ profesji,
                // możesz dokupić +10% (chyba że osiągnąłeś max).

                if ($nextLevel <= 2) { // Max +20%
                     $result[] = [
                        'id' => $item['id'],
                        'name' => '?', // Tu trzeba by pobrać nazwę z tabeli skills
                        'type' => 'upgrade', // Ulepszenie istniejącej
                        'target_level' => $nextLevel, // Np. z +0 na +10
                        'cost' => 100,
                        'source' => $item['source'] ?? 'profession'
                    ];
                }
            } else {
                // Nie masz tego wcale -> Możesz kupić bazę
                $result[] = [
                    'id' => $item['id'],
                    'details' => $item['details'],
                    'type' => 'new', // Nowa umiejętność
                    'target_level' => 0,
                    'cost' => 100,
                    'source' => $item['source'] ?? 'profession'
                ];
            }
        }

        return $result;
    }

    // ... (metody pomocnicze parseString i checkSkillStatus z poprzedniej wersji, dostosowane) ...

    private function parseString($str) {
        // Implementacja podobna jak wcześniej, rozbijająca string "12, 14(2)" na tablicę
        // Zwraca tablicę tablic ['id'=>12, 'details'=>null]
        // Dla uproszczenia tutaj pomijam pełny kod parsera, zakładając że go masz
        return [];
    }

    private function checkSkillStatus($item, $ownedList) {
        foreach($ownedList as $owned) {
            if ($owned['skill_id'] == $item['id']) {
                 // Sprawdzenie details (np. język)
                 if (($item['details'] ?? null) == ($owned['details'] ?? null)) {
                     return ['owned' => true, 'current_level' => $owned['level']];
                 }
            }
        }
        return ['owned' => false, 'current_level' => -1];
    }
}