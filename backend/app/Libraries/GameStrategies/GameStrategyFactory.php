<?php

namespace App\Libraries\GameStrategies;

class GameStrategyFactory
{
    public static function getStrategy(string $systemCode): GameSystemStrategyInterface
    {
        switch ($systemCode) {
            case 'wfrp2ed':
                return new Wfrp2edStrategy();
            
            // W przyszłości dodasz tutaj:
            // case 'dnd5e': return new Dnd5eStrategy();

            default:
                // Fallback: Prosta strategia "dodaj string do listy" dla nieznanych systemów
                return new class implements GameSystemStrategyInterface {
                    public function canPurchase(array $data, array $def): void {}
                    public function applyPurchase(array &$data, array $def): array {
                        $cat = ($def['category'] === 'umiejetnosc') ? 'skills' : 'talents';
                        if (!isset($data['attributes'][$cat])) $data['attributes'][$cat] = [];
                        $data['attributes'][$cat][] = $def['name'];
                        return ['added_generic' => $def['name']];
                    }
                };
        }
    }
}