<?php

namespace App\Services\Shop;

class ShopCurrencyService
{
    public function getCampaignCurrencyContext(int $campaignId): array
    {
        $campaign = \Config\Database::connect()
            ->table('campaigns')
            ->select('system_type')
            ->where('id', $campaignId)
            ->get()
            ->getRowArray();
        $systemCode = strtolower((string) ($campaign['system_type'] ?? 'generic'));
        $currencies = $this->definitionsForSystem($systemCode);

        return [
            'systemCode' => $systemCode,
            'defaultCurrencyCode' => $currencies[0]['code'],
            'currencies' => $currencies,
        ];
    }

    public function definitionsForSystem(string $systemCode): array
    {
        if (in_array(strtolower($systemCode), ['wfrp2ed', 'wfrp', 'warhammer'], true)) {
            return [
                [
                    'code' => 'wfrp_empire',
                    'labelPl' => 'Imperium',
                    'labelEn' => 'The Empire',
                    'units' => [
                        $this->unit('gold_crown', 'Złota korona', 'Złote korony', 'Gold crown', 'Gold crowns', 'zk', 'gc', 240, 'crown'),
                        $this->unit('silver_shilling', 'Srebrny szyling', 'Srebrne szylingi', 'Silver shilling', 'Silver shillings', 's', 'ss', 12, 'shilling'),
                        $this->unit('brass_penny', 'Miedziany pens', 'Miedziane pensy', 'Brass penny', 'Brass pennies', 'p', 'bp', 1, 'brass'),
                    ],
                ],
                [
                    'code' => 'wfrp_bretonnia',
                    'labelPl' => 'Bretonnia',
                    'labelEn' => 'Bretonnia',
                    'units' => [
                        $this->unit('ecu', 'Ecu', 'Ecu', 'Ecu', 'Ecu', 'ecu', 'ecu', 240, 'crown'),
                        $this->unit('denier', 'Denier', 'Denier', 'Denier', 'Denier', 'd', 'd', 1, 'brass'),
                    ],
                ],
            ];
        }

        if (in_array(strtolower($systemCode), ['coc7e', 'coc', 'call_of_cthulhu', 'cthulhu'], true)) {
            return [
                [
                    'code' => 'coc_usd_1920',
                    'labelPl' => 'Dolar amerykański (lata 20.)',
                    'labelEn' => 'US dollar (1920s)',
                    'units' => [
                        $this->unit('dollar', 'Dolar', 'Dolary', 'Dollar', 'Dollars', '$', '$', 100, 'unit'),
                        $this->unit('cent', 'Cent', 'Centy', 'Cent', 'Cents', '¢', '¢', 1, 'unit'),
                    ],
                ],
                [
                    'code' => 'coc_gbp_1920',
                    'labelPl' => 'Funt brytyjski (przed dziesiętny)',
                    'labelEn' => 'British pound (pre-decimal)',
                    'units' => [
                        $this->unit('pound', 'Funt', 'Funty', 'Pound', 'Pounds', '£', '£', 240, 'unit'),
                        $this->unit('shilling', 'Szyling', 'Szylingi', 'Shilling', 'Shillings', 's', 's', 12, 'unit'),
                        $this->unit('penny', 'Pens', 'Pensy', 'Penny', 'Pence', 'd', 'd', 1, 'unit'),
                    ],
                ],
                [
                    'code' => 'coc_frf_1920',
                    'labelPl' => 'Frank francuski (lata 20.)',
                    'labelEn' => 'French franc (1920s)',
                    'units' => [
                        $this->unit('franc', 'Frank', 'Franki', 'Franc', 'Francs', '₣', '₣', 100, 'unit'),
                        $this->unit('centime', 'Centym', 'Centymy', 'Centime', 'Centimes', 'c', 'c', 1, 'unit'),
                    ],
                ],
            ];
        }

        return [[
            'code' => 'generic',
            'labelPl' => 'Waluta systemowa',
            'labelEn' => 'System currency',
            'units' => [
                $this->unit('unit', 'Jednostka', 'Jednostki', 'Unit', 'Units', 'j', 'u', 1, 'unit'),
            ],
        ]];
    }

    private function unit(
        string $code,
        string $labelPl,
        string $labelPluralPl,
        string $labelEn,
        string $labelPluralEn,
        string $symbolPl,
        string $symbolEn,
        int $factor,
        string $icon
    ): array {
        return [
            'code' => $code,
            'labelPl' => $labelPl,
            'labelPluralPl' => $labelPluralPl,
            'labelEn' => $labelEn,
            'labelPluralEn' => $labelPluralEn,
            'symbolPl' => $symbolPl,
            'symbolEn' => $symbolEn,
            'factor' => $factor,
            'icon' => $icon,
        ];
    }
}
