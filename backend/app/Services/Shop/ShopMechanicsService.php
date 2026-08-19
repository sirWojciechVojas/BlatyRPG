<?php

namespace App\Services\Shop;

class ShopMechanicsService
{
    public function definitionsForSystem(string $systemCode): array
    {
        if (in_array(strtolower($systemCode), ['wfrp2ed', 'wfrp', 'warhammer'], true)) {
            return [
                'encumbrance' => [
                    'unit' => 'Punkty obciążenia',
                    'unitShort' => 'PO',
                    'allowCustom' => true,
                    'presets' => [
                        $this->preset(0, 'Brak', 'None', 'none'),
                        $this->preset(5, 'Minimalne', 'Minimal', 'minimal'),
                        $this->preset(10, 'Lekkie', 'Light', 'light'),
                        $this->preset(25, 'Małe', 'Low', 'low'),
                        $this->preset(50, 'Standardowe', 'Standard', 'standard'),
                        $this->preset(75, 'Ciężkie', 'Heavy', 'heavy'),
                        $this->preset(100, 'Bardzo ciężkie', 'Very heavy', 'very_heavy'),
                        $this->preset(150, 'Masywne', 'Massive', 'massive'),
                        $this->preset(200, 'Ekstremalne', 'Extreme', 'extreme'),
                    ],
                ],
            ];
        }

        return [
            'encumbrance' => [
                'unit' => 'Jednostki obciążenia',
                'unitShort' => 'j.',
                'allowCustom' => true,
                'presets' => [
                    $this->preset(0, 'Brak', 'None', 'none'),
                    $this->preset(1, 'Lekkie', 'Light', 'light'),
                    $this->preset(5, 'Standardowe', 'Standard', 'standard'),
                    $this->preset(10, 'Ciężkie', 'Heavy', 'heavy'),
                ],
            ],
        ];
    }

    private function preset(int $value, string $labelPl, string $labelEn, string $tone): array
    {
        return compact('value', 'labelPl', 'labelEn', 'tone');
    }
}
