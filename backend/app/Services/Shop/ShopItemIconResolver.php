<?php

namespace App\Services\Shop;

/**
 * Resolves a concrete inventory sprite from the item's meaning.
 *
 * The resolver deliberately uses names and genres before broad item classes.
 * This prevents an ARMOR fallback from replacing a shield with body armour.
 */
class ShopItemIconResolver
{
    private const LEGACY_GENERIC_ICONS = [
        'v0001', 'v0170', 'v0619', 'v0724', 'v1030', 'v1041', 'v1089', 'v1148',
    ];

    private const CLASS_FALLBACKS = [
        'ALCHEMY' => 'v1074',
        'ANIMAL' => 'v0653',
        'ARMAMENT' => 'v0189',
        'ARMOR' => 'v0328',
        'CLOTH' => 'v0360',
        'CUTLERY' => 'v0739',
        'FOOD' => 'v0093',
        'FORAGE' => 'v0197',
        'GADGET' => 'v1042',
        'JEWELLERY' => 'v0127',
        'MAGIC' => 'v0244',
        'MISC' => 'v1042',
        'POTION' => 'v1074',
        'POWDER' => 'v0467',
        'STATIONERY' => 'v0244',
        'TOOL' => 'v1058',
        'WEAPON' => 'v1289',
    ];

    public function resolve(
        array $item,
        ?string $currentIcon = null,
        bool $preserveExplicitIcon = false
    ): string
    {
        $name = (string) ($item['PERSONAL_PSEU'] ?? $item['NAME'] ?? $item['name_override'] ?? $item['name'] ?? $item['displayName'] ?? $item['templateName'] ?? '');
        $description = (string) ($item['PERSONAL_DESC'] ?? $item['DESCRIPTION'] ?? $item['description'] ?? $item['note'] ?? '');
        $itemClass = strtoupper(trim((string) ($item['ITEM_CLASS'] ?? $item['item_class'] ?? $item['itemClass'] ?? $item['classKey'] ?? '')));
        $itemGenre = strtoupper(trim((string) ($item['ITEM_GENRE'] ?? $item['item_genre'] ?? $item['itemGenre'] ?? $item['genreKey'] ?? '')));
        $current = strtolower(trim((string) ($currentIcon ?? $item['IMG_CLASS'] ?? $item['img_class'] ?? $item['imgClass'] ?? '')));

        // An icon sent explicitly by an editor is a user decision, including
        // sprites that were once used as generic defaults. Semantic cleanup is
        // reserved for records that did not carry an explicit icon choice.
        if ($preserveExplicitIcon && $this->isIconToken($current)) {
            return $current;
        }

        $rule = $this->matchingRule($name, $description, $itemGenre);

        if ($rule !== null) {
            $canonical = (string) $rule['icon'];
            if ($current === $canonical) {
                return $current;
            }
            // Preserve a deliberate icon-picker choice. The old generic icons
            // are replaced because they caused the mismatches fixed here.
            if ($this->isIconToken($current) && !in_array($current, self::LEGACY_GENERIC_ICONS, true)) {
                return $current;
            }
            return $canonical;
        }

        if ($this->isIconToken($current) && !in_array($current, self::LEGACY_GENERIC_ICONS, true)) {
            return $current;
        }

        return self::CLASS_FALLBACKS[$itemClass] ?? 'v0001';
    }

    private function matchingRule(string $name, string $description, string $genre): ?array
    {
        $nameTokens = $this->tokens($name);
        $descriptionTokens = $this->tokens($description);
        $bestRule = null;
        $bestScore = -INF;

        foreach ($this->rules() as $rule) {
            $score = -INF;
            foreach ((array) ($rule['patterns'] ?? []) as $pattern) {
                $weight = count($this->tokens((string) $pattern)) * 12;
                if ($this->matchesPattern($nameTokens, (string) $pattern)) {
                    $score = max($score, 400 + $weight);
                } elseif ($this->matchesPattern($descriptionTokens, (string) $pattern)) {
                    $score = max($score, 80 + $weight);
                }
            }
            if ($genre !== '' && in_array($genre, (array) ($rule['genres'] ?? []), true)) {
                $score = max($score, 300);
            }
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestRule = $rule;
            }
        }

        return is_finite($bestScore) ? $bestRule : null;
    }

    private function matchesPattern(array $tokens, string $pattern): bool
    {
        $expected = $this->tokens($pattern);
        if (!$expected) {
            return false;
        }
        foreach ($expected as $stem) {
            $matched = false;
            foreach ($tokens as $token) {
                if (strpos($token, $stem) === 0) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) {
                return false;
            }
        }
        return true;
    }

    private function tokens(string $value): array
    {
        $normalized = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ż' => 'z', 'ź' => 'z',
            'Ą' => 'a', 'Ć' => 'c', 'Ę' => 'e', 'Ł' => 'l', 'Ń' => 'n',
            'Ó' => 'o', 'Ś' => 's', 'Ż' => 'z', 'Ź' => 'z',
        ]);
        $normalized = strtolower($normalized);
        $normalized = preg_replace('/[^a-z0-9]+/', ' ', $normalized) ?? '';
        return array_values(array_filter(explode(' ', trim($normalized))));
    }

    private function isIconToken(string $value): bool
    {
        return (bool) preg_match('/^v\d{4}$/', $value);
    }

    private function rules(): array
    {
        return [
            ['icon' => 'v1233', 'patterns' => ['pawez']],
            ['icon' => 'v1252', 'patterns' => ['pukler']],
            ['icon' => 'v1240', 'patterns' => ['tarc', 'shield'], 'genres' => ['SHIELD']],
            ['icon' => 'v0496', 'patterns' => ['helm', 'przylbic', 'helmet'], 'genres' => ['HEAD']],
            ['icon' => 'v0441', 'patterns' => ['rekawic plyt', 'naramien', 'gauntlet']],
            ['icon' => 'v0443', 'patterns' => ['rekawic', 'glove']],
            ['icon' => 'v0318', 'patterns' => ['but', 'nagolenn', 'boots']],
            ['icon' => 'v0619', 'patterns' => ['kolczug', 'zbroj kolcz', 'mail armor']],
            ['icon' => 'v0597', 'patterns' => ['pancerz skor', 'zbroj skor']],
            ['icon' => 'v0328', 'patterns' => ['kirys', 'napier', 'zbroj plyt']],
            ['icon' => 'v0328', 'patterns' => ['zbroj', 'pancerz'], 'genres' => ['BODY']],
            ['icon' => 'v0360', 'patterns' => ['plaszcz', 'peleryn', 'cloak']],
            ['icon' => 'v0475', 'patterns' => ['kaptur', 'hood']],
            ['icon' => 'v1160', 'patterns' => ['szat', 'tunik', 'kaftan', 'robe']],
            ['icon' => 'v0222', 'patterns' => ['pas', 'belt']],
            ['icon' => 'v0738', 'patterns' => ['sakiew', 'sakw', 'torb', 'worek', 'bag']],
            ['icon' => 'v0189', 'patterns' => ['strzal', 'belt kusz', 'beltow', 'amunic']],
            ['icon' => 'v0189', 'patterns' => ['grot', 'lotk strzal']],
            ['icon' => 'v0543', 'patterns' => ['cieciw']],
            ['icon' => 'v0170', 'patterns' => ['kusz', 'arbalest', 'crossbow']],
            ['icon' => 'v0543', 'patterns' => ['luk', 'bow']],
            ['icon' => 'v1114', 'patterns' => ['rapier', 'szpad']],
            ['icon' => 'v0400', 'patterns' => ['sztylet', 'noz', 'kord', 'dagger', 'knife']],
            ['icon' => 'v1289', 'patterns' => ['miecz', 'ostrze', 'sword', 'blade']],
            ['icon' => 'v1262', 'patterns' => ['wloczn', 'pik', 'halabard', 'glewi', 'spear']],
            ['icon' => 'v0199', 'patterns' => ['topor', 'berdysz', 'axe', 'hatchet']],
            ['icon' => 'v1347', 'patterns' => ['mlot boj', 'morgenstern', 'war hammer']],
            ['icon' => 'v0610', 'patterns' => ['buzdygan', 'maczug', 'kiscien', 'mace', 'flail']],
            ['icon' => 'v1105', 'patterns' => ['kostur', 'quarterstaff']],
            ['icon' => 'v0665', 'patterns' => ['pistolet', 'rusznic', 'garla', 'firearm']],
            ['icon' => 'v0467', 'patterns' => ['proch', 'gunpowder']],
            ['icon' => 'v1075', 'patterns' => ['truciz', 'jad', 'toksyn', 'poison']],
            ['icon' => 'v0694', 'patterns' => ['masc', 'balsam', 'unguent', 'salve']],
            ['icon' => 'v1015', 'patterns' => ['kadzidl', 'incense']],
            ['icon' => 'v1074', 'patterns' => ['mikstur', 'eliksir', 'odtrut', 'nalewk', 'tonik'], 'genres' => ['POTION', 'HEALING', 'TOXINS']],
            ['icon' => 'v1074', 'patterns' => ['kropl', 'syrop', 'tinktur', 'napar']],
            ['icon' => 'v1074', 'patterns' => ['alembik', 'retort', 'mozdzierz', 'tygiel']],
            ['icon' => 'v1032', 'patterns' => ['odczynnik', 'kwas', 'saletr', 'siark', 'proszek', 'pyl wegl']],
            ['icon' => 'v1374', 'patterns' => ['fiolk', 'butelk', 'buklak', 'olej']],
            ['icon' => 'v0112', 'patterns' => ['chleb', 'bul', 'bochen', 'wypiek', 'loaf'], 'genres' => ['BAKERY']],
            ['icon' => 'v0109', 'patterns' => ['ser', 'cheese']],
            ['icon' => 'v0104', 'patterns' => ['mies', 'wedlin', 'dziczyz', 'meat']],
            ['icon' => 'v0110', 'patterns' => ['ryb', 'sledz', 'fish']],
            ['icon' => 'v0093', 'patterns' => ['gulasz', 'potraw', 'zup', 'kasz', 'stew'], 'genres' => ['MEALS']],
            ['icon' => 'v0082', 'patterns' => ['piw', 'ale', 'beer']],
            ['icon' => 'v0118', 'patterns' => ['win', 'miod pit', 'cydr', 'okowit'], 'genres' => ['DRINKS']],
            ['icon' => 'v0119', 'patterns' => ['owoc', 'jagod', 'fruit']],
            ['icon' => 'v1329', 'patterns' => ['warzyw', 'ogork', 'vegetable']],
            ['icon' => 'v0457', 'patterns' => ['zboz', 'owies', 'ziarn', 'grain']],
            ['icon' => 'v1032', 'patterns' => ['sol', 'przypraw', 'spice']],
            ['icon' => 'v0197', 'patterns' => ['ziol', 'korzen', 'krwawnik', 'grzyb', 'herb']],
            ['icon' => 'v0244', 'patterns' => ['ksieg', 'ksiazk', 'modlitewn', 'grymuar', 'book']],
            ['icon' => 'v1368', 'patterns' => ['kart', 'talia', 'runiczn kosci']],
            ['icon' => 'v1230', 'patterns' => ['map', 'plan traktu']],
            ['icon' => 'v0963', 'patterns' => ['pior', 'stalowk', 'atrament', 'quill']],
            ['icon' => 'v0640', 'patterns' => ['pieczec', 'plomb', 'lak', 'seal']],
            ['icon' => 'v1195', 'patterns' => ['pergamin', 'zwoj', 'dokument', 'list', 'scroll']],
            ['icon' => 'v1151', 'patterns' => ['piersc srebr', 'silver ring']],
            ['icon' => 'v1127', 'patterns' => ['piersc', 'sygnet', 'ring']],
            ['icon' => 'v0585', 'patterns' => ['brosz', 'brooch']],
            ['icon' => 'v0685', 'patterns' => ['bransolet', 'bracelet']],
            ['icon' => 'v0127', 'patterns' => ['amulet', 'medalik', 'medalion', 'talizman']],
            ['icon' => 'v0609', 'patterns' => ['wytrych', 'lockpick']],
            ['icon' => 'v0555', 'patterns' => ['klucz', 'key']],
            ['icon' => 'v1041', 'patterns' => ['latar', 'lamp', 'lantern']],
            ['icon' => 'v1304', 'patterns' => ['pochod', 'swiec', 'torch']],
            ['icon' => 'v0437', 'patterns' => ['krzesiw', 'hubk', 'flint']],
            ['icon' => 'v1030', 'patterns' => ['lin', 'powroz', 'sznur', 'rope']],
            ['icon' => 'v1030', 'patterns' => ['blok kraz', 'bloczek']],
            ['icon' => 'v1362', 'patterns' => ['sidla', 'pulapk', 'snare', 'trap']],
            ['icon' => 'v1058', 'patterns' => ['mlot', 'mlotek', 'hammer']],
            ['icon' => 'v0451', 'patterns' => ['sztab', 'gwozd', 'nit', 'pret', 'okuci', 'ingot']],
            ['icon' => 'v0555', 'patterns' => ['klodk', 'zamek', 'zawias']],
            ['icon' => 'v1042', 'patterns' => ['skrzyn', 'pudel', 'puzder', 'beczk', 'crate']],
            ['icon' => 'v1368', 'patterns' => ['kosci', 'dice']],
            ['icon' => 'v1373', 'patterns' => ['uprzaz', 'siodl', 'uzd', 'harness']],
            ['icon' => 'v0653', 'patterns' => ['kon', 'mul', 'wierzch', 'horse']],
            ['icon' => 'v0650', 'patterns' => ['pies', 'ogar', 'dog']],
            ['icon' => 'v1259', 'patterns' => ['ges', 'ptak', 'bird']],
            ['icon' => 'v1361', 'patterns' => ['plotn', 'filc', 'wsteg', 'wstaz', 'koc']],
            ['icon' => 'v1304', 'patterns' => ['wosk', 'knot']],
            ['icon' => 'v1088', 'patterns' => ['lustro', 'lusterk', 'zwierciadl']],
            ['icon' => 'v0802', 'patterns' => ['lancuch', 'lancuszek']],
            ['icon' => 'v0739', 'patterns' => ['kubek', 'mis', 'garnek', 'kielich', 'chalice']],
        ];
    }
}
