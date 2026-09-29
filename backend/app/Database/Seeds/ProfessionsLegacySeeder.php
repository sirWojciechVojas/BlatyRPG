<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;
use App\Services\Profession\ProfessionPathResolver;

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

        $catalogById = [];
        foreach ($rows as $row) {
            $id = (int)($row[0] ?? 0);
            $name = $row[1] ?? '';
            if ($id && $name) {
                $catalogById[$id] = $name;
            }
        }
        $pathResolver = new ProfessionPathResolver();
        $nameIndex = $pathResolver->nameIndex($catalogById);

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
                $paths = array_merge($paths, $this->buildPaths(
                    $id,
                    $entryRaw,
                    $nameIndex,
                    'entry',
                    $pathResolver
                ));
            }

            $exitRaw = trim((string)($row[24] ?? ''));
            if ($exitRaw !== '') {
                $paths = array_merge($paths, $this->buildPaths(
                    $id,
                    $exitRaw,
                    $nameIndex,
                    'exit',
                    $pathResolver
                ));
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

    private function buildPaths(
        int $professionId,
        string $raw,
        array $nameIndex,
        string $relationType,
        ProfessionPathResolver $resolver
    ): array
    {
        $paths = [];
        foreach ($resolver->resolveList($raw, $nameIndex) as $item) {
            $relatedId = (int) ($item['professionId'] ?? 0);
            if ($relatedId < 1) {
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
