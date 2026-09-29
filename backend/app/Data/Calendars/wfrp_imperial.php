<?php

/**
 * Imperial calendar for the Old World. The order of this file is the order
 * used by the engine; clients never carry a second copy of these rules.
 */
return [
    'key' => 'wfrp-imperial',
    'name' => 'Kalendarz Imperialny',
    'locale' => 'pl',
    'era' => ['suffix' => 'KI', 'name' => 'Kalendarz Imperialny'],
    'dateFormat' => [
        'regular' => '{day} {month}, {year} {era}',
        'intercalary' => '{special}, {year} {era}',
        'time' => '{date}, {time}',
    ],
    'defaultState' => [
        'year' => 2522,
        'dayOfYear' => 2,
        'minuteOfDay' => 480,
        'showTime' => false,
        'running' => false,
    ],
    'week' => [
        'names' => [
            'Dzień Pracy',
            'Dzień Poboru',
            'Dzień Targowy',
            'Dzień Wypieków',
            'Dzień Podatków',
            'Dzień Królewski',
            'Dzień Początku',
            'Dzień Świąteczny',
        ],
        'anchor' => ['year' => 2522, 'monthKey' => 'powiedzime', 'day' => 1, 'weekdayIndex' => 0],
        'intercalaryAdvancesWeek' => false,
    ],
    'months' => [
        ['key' => 'powiedzime', 'name' => 'Powiedźmie', 'length' => 32],
        ['key' => 'zmiana-roku', 'name' => 'Zmiana Roku', 'length' => 33],
        ['key' => 'czas-orki', 'name' => 'Czas Orki', 'length' => 33],
        ['key' => 'czas-sigmara', 'name' => 'Czas Sigmara', 'length' => 33],
        ['key' => 'czas-lata', 'name' => 'Czas Lata', 'length' => 33],
        ['key' => 'przed-tajemnica', 'name' => 'Przed Tajemnicą', 'length' => 33],
        ['key' => 'po-tajemnicy', 'name' => 'Po Tajemnicy', 'length' => 32],
        ['key' => 'czas-zbiorow', 'name' => 'Czas Zbiorów', 'length' => 33],
        ['key' => 'czas-warzenia', 'name' => 'Czas Warzenia', 'length' => 33],
        ['key' => 'czas-mrozow', 'name' => 'Czas Mrozów', 'length' => 33],
        ['key' => 'czas-ulryka', 'name' => 'Czas Ulryka', 'length' => 33],
        ['key' => 'przedwiedzime', 'name' => 'Przedwiedźmie', 'length' => 33],
    ],
    'intercalaryDays' => [
        ['key' => 'noc-wiedzm', 'name' => 'Noc Wiedźm', 'afterMonthKey' => null],
        ['key' => 'rozkwitanie', 'name' => 'Rozkwitanie', 'afterMonthKey' => 'zmiana-roku'],
        ['key' => 'dzien-slonca', 'name' => 'Dzień Słońca', 'afterMonthKey' => 'czas-lata'],
        ['key' => 'noc-tajemnicy', 'name' => 'Noc Tajemnicy', 'afterMonthKey' => 'przed-tajemnica'],
        ['key' => 'przekwitanie', 'name' => 'Przekwitanie', 'afterMonthKey' => 'czas-zbiorow'],
        ['key' => 'uspienie', 'name' => 'Uśpienie', 'afterMonthKey' => 'czas-ulryka'],
    ],
    'seasons' => [
        ['key' => 'wiosna', 'name' => 'Wiosna', 'start' => ['monthKey' => 'powiedzime', 'day' => 17]],
        ['key' => 'lato', 'name' => 'Lato', 'start' => ['monthKey' => 'czas-sigmara', 'day' => 18]],
        ['key' => 'jesien', 'name' => 'Jesień', 'start' => ['monthKey' => 'po-tajemnicy', 'day' => 17]],
        ['key' => 'zima', 'name' => 'Zima', 'start' => ['monthKey' => 'czas-mrozow', 'day' => 18]],
    ],
    'holidays' => [
        ['key' => 'noc-wiedzm', 'name' => 'Noc Wiedźm', 'specialDayKey' => 'noc-wiedzm'],
        ['key' => 'rozkwitanie', 'name' => 'Rozkwitanie', 'specialDayKey' => 'rozkwitanie'],
        ['key' => 'dzien-slonca', 'name' => 'Dzień Słońca', 'specialDayKey' => 'dzien-slonca'],
        ['key' => 'noc-tajemnicy', 'name' => 'Noc Tajemnicy', 'specialDayKey' => 'noc-tajemnicy'],
        ['key' => 'przekwitanie', 'name' => 'Przekwitanie', 'specialDayKey' => 'przekwitanie'],
        ['key' => 'uspienie', 'name' => 'Uśpienie', 'specialDayKey' => 'uspienie'],
    ],
    'moonCycles' => [
        'mannslieb' => [
            'name' => 'Mannslieb',
            'type' => 'cycle',
            'periodDays' => 25,
            'anchor' => ['year' => 2522, 'dayOfYear' => 1, 'offset' => 0],
            'phases' => [
                ['key' => 'now', 'name' => 'Nów', 'illumination' => 0.0, 'waxing' => true],
                ['key' => 'sierp-przybywajacy', 'name' => 'Sierp przybywający', 'illumination' => 0.25, 'waxing' => true],
                ['key' => 'pierwsza-kwadra', 'name' => 'Pierwsza kwadra', 'illumination' => 0.5, 'waxing' => true],
                ['key' => 'garbaty-przybywajacy', 'name' => 'Księżyc garbaty przybywający', 'illumination' => 0.75, 'waxing' => true],
                ['key' => 'pelnia', 'name' => 'Pełnia', 'illumination' => 1.0, 'waxing' => false],
                ['key' => 'garbaty-ubywajacy', 'name' => 'Księżyc garbaty ubywający', 'illumination' => 0.75, 'waxing' => false],
                ['key' => 'ostatnia-kwadra', 'name' => 'Ostatnia kwadra', 'illumination' => 0.5, 'waxing' => false],
                ['key' => 'sierp-ubywajacy', 'name' => 'Sierp ubywający', 'illumination' => 0.25, 'waxing' => false],
            ],
            'phaseByOffset' => [
                'now', 'now',
                'sierp-przybywajacy', 'sierp-przybywajacy', 'sierp-przybywajacy',
                'pierwsza-kwadra', 'pierwsza-kwadra', 'pierwsza-kwadra',
                'garbaty-przybywajacy', 'garbaty-przybywajacy', 'garbaty-przybywajacy',
                'pelnia', 'pelnia', 'pelnia', 'pelnia',
                'garbaty-ubywajacy', 'garbaty-ubywajacy', 'garbaty-ubywajacy',
                'ostatnia-kwadra', 'ostatnia-kwadra', 'ostatnia-kwadra',
                'sierp-ubywajacy', 'sierp-ubywajacy', 'sierp-ubywajacy',
                'now',
            ],
        ],
        'morrslieb' => [
            'name' => 'Morrslieb',
            'type' => 'manual',
            'phases' => [
                ['key' => 'now', 'name' => 'Nów'],
                ['key' => 'sierp', 'name' => 'Sierp'],
                ['key' => 'polowka', 'name' => 'Połowa'],
                ['key' => 'garbaty', 'name' => 'Księżyc garbaty'],
                ['key' => 'pelnia', 'name' => 'Pełnia'],
            ],
        ],
    ],
    'settingMatch' => [
        'systemCodes' => ['wfrp2ed', 'wfrp4e', 'wfrp', 'warhammer'],
        'universeCodes' => ['old_world'],
    ],
];
