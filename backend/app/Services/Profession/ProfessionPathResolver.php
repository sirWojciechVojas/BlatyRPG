<?php

namespace App\Services\Profession;

use App\Database\Seeds\Data\WfrpData;

final class ProfessionPathResolver
{
    private const SYSTEM_CODE = 'wfrp2ed';

    private const ALIASES = [
        'dowolna profesja cyrkowca' => 'cyrkowiec',
        'herszta banitow' => 'herszt banitow',
        'mistrz gilidii' => 'mistrz gildii',
        'wloczega' => 'wloczykij',
        'zwazdca' => 'zwadzca',
    ];

    /**
     * Restores readable legacy entry/exit paths and links every name that can
     * be matched safely to the current system catalog.
     *
     * @return array<int, array{entries: array<int, array<string, mixed>>, exits: array<int, array<string, mixed>>}>
     */
    public function legacyPaths(string $systemCode, array $professions): array
    {
        if (strtolower(trim($systemCode)) !== self::SYSTEM_CODE
            || !class_exists(WfrpData::class)
            || !method_exists(WfrpData::class, 'getProfessions')) {
            return [];
        }

        $catalogById = [];
        foreach ($professions as $profession) {
            $id = (int) ($profession['id'] ?? 0);
            $name = trim((string) ($profession['name'] ?? ''));
            if ($id > 0 && $name !== '') {
                $catalogById[$id] = $name;
            }
        }
        if (!$catalogById) {
            return [];
        }

        $nameIndex = $this->nameIndex($catalogById);
        $result = [];
        foreach (WfrpData::getProfessions() as $row) {
            $id = (int) ($row[0] ?? 0);
            $legacyName = trim((string) ($row[1] ?? ''));
            if (!isset($catalogById[$id])
                || $this->normalize($catalogById[$id])
                    !== $this->normalize($legacyName)) {
                continue;
            }

            $entries = $this->resolveList((string) ($row[23] ?? ''), $nameIndex);
            $exits = $this->resolveList((string) ($row[24] ?? ''), $nameIndex);
            if ($entries || $exits) {
                $result[$id] = ['entries' => $entries, 'exits' => $exits];
            }
        }

        return $result;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveList(string $raw, array $nameIndex): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }

        $normalizedSeparators = str_replace(
            ["\\r\\n", "\\n", "\\r", "\r\n", "\n", "\r", ';'],
            ',',
            $raw
        );
        $normalizedSeparators = preg_replace(
            '/\.(?=\s*[\p{Lu}])/u',
            ',',
            $normalizedSeparators
        ) ?: $normalizedSeparators;

        $items = [];
        $seen = [];
        foreach (explode(',', $normalizedSeparators) as $part) {
            $name = $this->cleanLabel($part);
            if ($name === '') {
                continue;
            }

            $professionId = $this->resolveId($name, $nameIndex);
            $dedupeKey = $professionId
                ? 'id:' . $professionId
                : 'name:' . $this->normalize($name);
            if (isset($seen[$dedupeKey])) {
                continue;
            }
            $seen[$dedupeKey] = true;
            $items[] = [
                'professionId' => $professionId,
                'name' => $professionId
                    ? (string) $nameIndex['names'][$professionId]
                    : $name,
                'linked' => $professionId !== null,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, string> $catalogById
     */
    public function nameIndex(array $catalogById): array
    {
        $ids = [];
        $names = [];
        foreach ($catalogById as $id => $name) {
            $id = (int) $id;
            $name = trim((string) $name);
            if ($id < 1 || $name === '') {
                continue;
            }

            $names[$id] = $name;
            foreach ($this->nameCandidates($name) as $candidate) {
                if ($candidate !== '' && !isset($ids[$candidate])) {
                    $ids[$candidate] = $id;
                }
            }
        }

        return ['ids' => $ids, 'names' => $names];
    }

    private function resolveId(string $name, array $nameIndex): ?int
    {
        foreach ($this->nameCandidates($name) as $candidate) {
            $candidate = self::ALIASES[$candidate] ?? $candidate;
            if (isset($nameIndex['ids'][$candidate])) {
                return (int) $nameIndex['ids'][$candidate];
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function nameCandidates(string $name): array
    {
        $candidates = [$this->normalize($name)];
        $withoutQualifier = preg_replace('/\s*\([^)]*\)\s*$/u', '', $name);
        if (is_string($withoutQualifier)) {
            $candidates[] = $this->normalize($withoutQualifier);
        }

        return array_values(array_unique(array_filter($candidates)));
    }

    private function cleanLabel(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?: trim($value);
        return trim($value, " \t\n\r\0\x0B-–—.;:");
    }

    private function normalize(string $value): string
    {
        $value = function_exists('mb_strtolower')
            ? mb_strtolower(trim($value), 'UTF-8')
            : strtolower(trim($value));
        $value = strtr($value, [
            'ą' => 'a', 'ć' => 'c', 'ę' => 'e', 'ł' => 'l', 'ń' => 'n',
            'ó' => 'o', 'ś' => 's', 'ź' => 'z', 'ż' => 'z', '’' => "'",
        ]);
        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: $value;
        return trim(preg_replace('/\s+/u', ' ', $value) ?: $value);
    }
}
