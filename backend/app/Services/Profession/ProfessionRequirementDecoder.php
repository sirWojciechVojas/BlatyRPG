<?php

namespace App\Services\Profession;

use App\Database\Seeds\Data\WfrpData;
use CodeIgniter\Database\BaseConnection;

/** Decodes legacy profession requirement expressions without losing their choices. */
final class ProfessionRequirementDecoder
{
    private $db;
    private $catalogs = [];

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function decode(string $raw, string $kind, int $systemId = 0): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [
                'raw' => '',
                'display' => '',
                'decoded' => true,
                'items' => [],
            ];
        }

        $kind = strtolower($kind) === 'talents' ? 'talents' : 'skills';
        try {
            $display = $this->decodeExpression($raw, $kind, $systemId);
            $items = $this->decodeItems($raw, $kind, $systemId);
        } catch (\InvalidArgumentException $exception) {
            return [
                'raw' => $raw,
                'display' => $raw,
                'decoded' => false,
                'items' => [[
                    'raw' => $raw,
                    'display' => $raw,
                    'decoded' => false,
                ]],
            ];
        }

        return [
            'raw' => $raw,
            'display' => $display,
            'decoded' => $display !== $raw,
            'items' => $items,
        ];
    }

    /** Returns selectable legacy atoms, including every known specialization. */
    public function options(string $kind, int $systemId = 0): array
    {
        $kind = strtolower($kind) === 'talents' ? 'talents' : 'skills';
        $catalog = $this->catalog($kind, $systemId);
        $options = [];
        foreach ($catalog['definitions'] as $legacyId => $definition) {
            $raw = (string) $legacyId;
            $options[] = [
                'raw' => $raw,
                'display' => (string) $definition['name'],
                'name' => (string) $definition['name'],
                'legacyId' => (int) $legacyId,
                'specializationId' => null,
            ];
            foreach (
                $catalog['specializations'][$definition['key']] ?? []
                as $specializationId => $specialization
            ) {
                $specializedRaw = $raw . '(' . $specializationId . ')';
                $options[] = [
                    'raw' => $specializedRaw,
                    'display' => $this->decodeExpression(
                        $specializedRaw,
                        $kind,
                        $systemId
                    ),
                    'name' => (string) $definition['name'],
                    'legacyId' => (int) $legacyId,
                    'specializationId' => (int) $specializationId,
                ];
            }
        }
        usort($options, static function (array $left, array $right): int {
            return strnatcasecmp($left['display'], $right['display'])
                ?: strcmp($left['raw'], $right['raw']);
        });
        return $options;
    }

    private function decodeItems(
        string $raw,
        string $kind,
        int $systemId
    ): array {
        $items = [];
        foreach ($this->splitTopLevel($raw, ',') as $item) {
            $item = trim($item);
            if ($item === '') continue;
            $display = $this->decodeExpression($item, $kind, $systemId);
            $items[] = [
                'raw' => $item,
                'display' => $display,
                'decoded' => $display !== $item,
            ];
        }
        return $items;
    }

    private function decodeExpression(
        string $expression,
        string $kind,
        int $systemId
    ): string {
        $items = $this->splitTopLevel($expression, ',');
        $decoded = [];
        foreach ($items as $item) {
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            $choices = $this->splitTopLevel($item, '|');
            $choiceLabels = [];
            foreach ($choices as $choice) {
                $choiceLabels[] = $this->decodeAtom(
                    trim($choice),
                    $kind,
                    $systemId
                );
            }
            $decoded[] = implode(' lub ', $choiceLabels);
        }
        if (!$decoded) {
            throw new \InvalidArgumentException('Empty requirement expression.');
        }
        return implode(', ', $decoded);
    }

    private function decodeAtom(string $atom, string $kind, int $systemId): string
    {
        if (strlen($atom) >= 2 && $atom[0] === '['
            && substr($atom, -1) === ']') {
            return '(' . $this->decodeExpression(
                substr($atom, 1, -1),
                $kind,
                $systemId
            ) . ')';
        }
        if (!preg_match('/^(\d+)(?:\((.*)\))?$/us', $atom, $matches)) {
            throw new \InvalidArgumentException('Unsupported requirement atom.');
        }

        $catalog = $this->catalog($kind, $systemId);
        $legacyId = (int) $matches[1];
        if (!isset($catalog['definitions'][$legacyId])) {
            return '#' . $legacyId
                . (isset($matches[2]) ? '(' . trim($matches[2]) . ')' : '');
        }
        $definition = $catalog['definitions'][$legacyId];
        $name = (string) $definition['name'];
        if (!isset($matches[2])) {
            return $name;
        }

        $specialization = $this->decodeSpecialization(
            trim($matches[2]),
            (string) $definition['key'],
            $catalog['specializations']
        );
        $baseName = trim((string) preg_replace(
            '/\s*\(\s*różne\s*\)\s*$/iu',
            '',
            $name
        ));
        return $baseName . ' (' . $specialization . ')';
    }

    private function decodeSpecialization(
        string $expression,
        string $definitionKey,
        array $specializations
    ): string {
        if ($expression === '') {
            return 'dowolna';
        }
        $parts = preg_split('/([,|])/u', $expression, -1, PREG_SPLIT_DELIM_CAPTURE);
        $result = '';
        foreach ($parts ?: [] as $part) {
            if ($part === ',') {
                $result = rtrim($result) . ', ';
                continue;
            }
            if ($part === '|') {
                $result = rtrim($result) . ' lub ';
                continue;
            }
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^(\d+)(.*)$/us', $part, $matches)) {
                $result .= $part;
                continue;
            }
            $localId = (int) $matches[1];
            $suffix = trim($matches[2]);
            if ($localId === 0) {
                $label = $suffix !== '' ? 'dowolne ' . ltrim($suffix, ': ') : 'dowolna';
                $suffix = '';
            } else {
                $label = $specializations[$definitionKey][$localId] ?? '#' . $localId;
            }
            if ($suffix !== '') {
                $label .= str_starts_with($suffix, ':')
                    ? ': ' . trim(substr($suffix, 1))
                    : ' ' . $suffix;
            }
            $result .= $label;
        }
        return trim($result);
    }

    private function catalog(string $kind, int $systemId): array
    {
        $cacheKey = $kind . ':' . $systemId;
        if (isset($this->catalogs[$cacheKey])) {
            return $this->catalogs[$cacheKey];
        }

        $rows = $kind === 'talents'
            ? WfrpData::getTalents()
            : WfrpData::getSkills();
        $definitions = [];
        foreach ($rows as $row) {
            $legacyId = (int) ($row[0] ?? 0);
            $name = trim((string) ($row[1] ?? ''));
            if ($legacyId > 0 && $name !== '') {
                $definitions[$legacyId] = [
                    'name' => $name,
                    'key' => $this->key($name),
                ];
            }
        }

        if ($systemId > 0 && $this->db->tableExists('game_definitions')) {
            $category = $kind === 'talents' ? 'zdolnosc' : 'umiejetnosc';
            $databaseRows = $this->db->table('game_definitions')
                ->select('name,metadata')
                ->where('system_id', $systemId)
                ->where('category', $category)
                ->get()
                ->getResultArray();
            foreach ($databaseRows as $row) {
                $metadata = $this->jsonObject($row['metadata'] ?? null);
                $legacyId = (int) ($metadata['legacy_id'] ?? 0);
                $name = trim((string) ($row['name'] ?? ''));
                if ($legacyId > 0 && $name !== '') {
                    $definitions[$legacyId] = [
                        'name' => $name,
                        'key' => $this->key($name),
                    ];
                }
            }
        }

        $specializations = [];
        foreach (WfrpData::getSpecializations() as $row) {
            $localId = (int) ($row[1] ?? 0);
            $key = $this->key((string) ($row[2] ?? ''));
            $name = trim((string) ($row[3] ?? ''));
            $alias = trim((string) ($row[6] ?? ''));
            if ($key === '' || $localId < 1 || $name === '') {
                continue;
            }
            $specializations[$key][$localId] = $alias !== ''
                && $key === 'znajomoscjezyka'
                ? $name . ' — ' . $alias
                : $name;
        }

        return $this->catalogs[$cacheKey] = [
            'definitions' => $definitions,
            'specializations' => $specializations,
        ];
    }

    private function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $buffer = '';
        $round = 0;
        $square = 0;
        $length = strlen($value);
        for ($index = 0; $index < $length; $index++) {
            $character = $value[$index];
            if ($character === '(') $round++;
            if ($character === ')') $round--;
            if ($character === '[') $square++;
            if ($character === ']') $square--;
            if ($round < 0 || $square < 0) {
                throw new \InvalidArgumentException('Unbalanced expression.');
            }
            if ($character === $separator && $round === 0 && $square === 0) {
                $parts[] = $buffer;
                $buffer = '';
                continue;
            }
            $buffer .= $character;
        }
        if ($round !== 0 || $square !== 0) {
            throw new \InvalidArgumentException('Unbalanced expression.');
        }
        $parts[] = $buffer;
        return $parts;
    }

    private function key(string $value): string
    {
        $value = preg_replace('/\s*\(\s*różne\s*\)\s*$/iu', '', $value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $value);
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $ascii));
    }

    private function jsonObject($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || trim($value) === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
