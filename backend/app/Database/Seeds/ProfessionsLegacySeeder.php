<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;

class ProfessionsLegacySeeder extends WfrpBaseSeeder
{
    public function run()
    {
        if (!$this->ensureWfrpDataAvailable()) {
            return;
        }

        $db = \Config\Database::connect();
        $systemId = $this->resolveSystemId($db);
        if (!$systemId) {
            return;
        }

        $existing = $db->table('professions')->where('system_id', $systemId)->countAllResults();
        if ($existing > 0) {
            echo "Professions for {$this->systemCode} already exist. Skipping.\n";
            return;
        }

        $this->db->disableForeignKeyChecks();

        $data = $this->getProfessionsData($systemId);

        if (empty($data['professions'])) {
            $this->db->enableForeignKeyChecks();
            return;
        }

        foreach (array_chunk($data['professions'], 100) as $chunk) {
            $this->db->table('professions')->insertBatch($chunk);
        }

        if (!empty($data['attributes'])) {
            foreach (array_chunk($data['attributes'], 200) as $chunk) {
                $this->db->table('profession_attributes')->insertBatch($chunk);
            }
        }

        if (!empty($data['definitions'])) {
            foreach (array_chunk($data['definitions'], 200) as $chunk) {
                $this->db->table('profession_definitions')->insertBatch($chunk);
            }
        }

        if (!empty($data['paths'])) {
            foreach (array_chunk($data['paths'], 200) as $chunk) {
                $this->db->table('profession_paths')->insertBatch($chunk);
            }
        }

        if (!empty($data['equipment'])) {
            foreach (array_chunk($data['equipment'], 200) as $chunk) {
                $this->db->table('profession_equipment')->insertBatch($chunk);
            }
        }

        $this->db->enableForeignKeyChecks();
    }

    private function getProfessionsData(int $systemId): array
    {
        if (!class_exists(WfrpData::class) || !method_exists(WfrpData::class, 'getProfessions')) {
            echo "Brak danych w WfrpData::getProfessions().\n";
            return [
                'professions' => [],
                'attributes' => [],
                'definitions' => [],
                'paths' => [],
                'equipment' => [],
            ];
        }

        $rows = WfrpData::getProfessions();
        $now = date('Y-m-d H:i:s');

        $nameMap = [];
        foreach ($rows as $row) {
            $id = (int)($row[0] ?? 0);
            $name = $row[1] ?? '';
            if ($id && $name) {
                $nameMap[$this->normalizeName($name)] = $id;
            }
        }

        $primaryAttributes = [
            'weapon_skill' => 4,
            'ballistic_skill' => 5,
            'strength' => 6,
            'toughness' => 7,
            'agility' => 8,
            'intelligence' => 9,
            'willpower' => 10,
            'fellowship' => 11,
        ];

        $secondaryAttributes = [
            'attacks' => 12,
            'wounds' => 13,
            'strength_bonus' => 14,
            'toughness_bonus' => 15,
            'movement' => 16,
            'magic' => 17,
            'insanity_points' => 18,
            'fate_points' => 19,
        ];

        $professions = [];
        $attributes = [];
        $definitions = [];
        $paths = [];
        $equipment = [];

        foreach ($rows as $row) {
            $id = (int)($row[0] ?? 0);
            if (!$id) {
                continue;
            }

            $professions[] = [
                'id' => $id,
                'system_id' => $systemId,
                'name' => $row[1] ?? '',
                'description' => $row[2] ?? '',
                'details' => $row[3] ?? null,
                'is_advanced' => $this->normalizeBool($row[25] ?? false),
                'is_main' => $this->normalizeBool($row[26] ?? false),
                'created_at' => $now,
                'updated_at' => $now,
            ];

            foreach ($primaryAttributes as $key => $index) {
                $attributes[] = [
                    'profession_id' => $id,
                    'attribute_key' => $key,
                    'attribute_group' => 'primary',
                    'value' => (int)($row[$index] ?? 0),
                ];
            }

            foreach ($secondaryAttributes as $key => $index) {
                $attributes[] = [
                    'profession_id' => $id,
                    'attribute_key' => $key,
                    'attribute_group' => 'secondary',
                    'value' => (int)($row[$index] ?? 0),
                ];
            }

            $skillsRaw = trim((string)($row[20] ?? ''));
            if ($skillsRaw !== '') {
                $definitions[] = [
                    'profession_id' => $id,
                    'definition_id' => null,
                    'metadata' => json_encode([
                        'list_type' => 'skills',
                        'raw' => $skillsRaw,
                    ], JSON_INVALID_UTF8_SUBSTITUTE),
                ];
            }

            $talentsRaw = trim((string)($row[21] ?? ''));
            if ($talentsRaw !== '') {
                $definitions[] = [
                    'profession_id' => $id,
                    'definition_id' => null,
                    'metadata' => json_encode([
                        'list_type' => 'talents',
                        'raw' => $talentsRaw,
                    ], JSON_INVALID_UTF8_SUBSTITUTE),
                ];
            }

            $equipmentRaw = trim((string)($row[22] ?? ''));
            if ($equipmentRaw !== '') {
                $equipment[] = [
                    'profession_id' => $id,
                    'definition_id' => null,
                    'item_name' => null,
                    'quantity' => 1,
                    'notes' => $equipmentRaw,
                ];
            }

            $entryRaw = trim((string)($row[23] ?? ''));
            if ($entryRaw !== '') {
                $paths = array_merge($paths, $this->buildPaths($id, $entryRaw, $nameMap, 'entry'));
            }

            $exitRaw = trim((string)($row[24] ?? ''));
            if ($exitRaw !== '') {
                $paths = array_merge($paths, $this->buildPaths($id, $exitRaw, $nameMap, 'exit'));
            }
        }

        return [
            'professions' => $professions,
            'attributes' => $attributes,
            'definitions' => $definitions,
            'paths' => $paths,
            'equipment' => $equipment,
        ];
    }

    private function normalizeBool($value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);
            return in_array($normalized, ['1', 'true', 'yes'], true) ? 1 : 0;
        }

        return $value ? 1 : 0;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);
        if (function_exists('mb_strtolower')) {
            return mb_strtolower($name);
        }

        return strtolower($name);
    }

    private function buildPaths(int $professionId, string $raw, array $nameMap, string $relationType): array
    {
        $normalized = str_replace(';', ',', $raw);
        $parts = array_filter(array_map('trim', explode(',', $normalized)));
        if (empty($parts)) {
            return [];
        }

        $paths = [];
        foreach ($parts as $name) {
            if ($name === '') {
                continue;
            }

            $key = $this->normalizeName($name);
            $relatedId = $nameMap[$key] ?? null;
            if (!$relatedId) {
                continue;
            }

            $paths[] = [
                'profession_id' => $professionId,
                'related_profession_id' => $relatedId,
                'relation_type' => $relationType,
            ];
        }

        return $paths;
    }
}
