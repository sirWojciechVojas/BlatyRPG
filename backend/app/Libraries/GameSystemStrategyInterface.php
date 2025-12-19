<?php

namespace App\Libraries\GameStrategies;

interface GameSystemStrategyInterface
{
    /**
     * Sprawdza, czy postać spełnia wymagania (np. profesję, rasę).
     * Rzuca wyjątek, jeśli nie.
     */
    public function canPurchase(array $characterData, array $definition): void;

    /**
     * Aplikuje zmiany do danych postaci (np. dodaje +5 do S, dodaje talent do listy).
     * Zwraca tablicę z opisem zmian.
     */
    public function applyPurchase(array &$characterData, array $definition): array;
}