<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Data\WfrpData;
use CodeIgniter\Database\Seeder;

class ProfessionsLegacySeeder extends Seeder
{
    public function run()
    {
        $this->db->disableForeignKeyChecks();

        $data = $this->getProfessionsData();

        if (empty($data)) {
            $this->db->enableForeignKeyChecks();
            return;
        }

        $now = date('Y-m-d H:i:s');
        foreach ($data as &$row) {
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }

        foreach (array_chunk($data, 100) as $chunk) {
            $this->db->table('professions')->insertBatch($chunk);
        }

        $this->db->enableForeignKeyChecks();
    }

    private function getProfessionsData(): array
    {
        if (!class_exists(WfrpData::class) || !method_exists(WfrpData::class, 'getProfessions')) {
            echo "Brak danych w WfrpData::getProfessions().\n";
            return [];
        }

        $rows = WfrpData::getProfessions();

        return array_map(function (array $row) {
            return [
                'name' => $row[1] ?? '',
                'description' => $row[2] ?? '',
                'details' => $row[3] ?? null,
                'weapon_skill' => (int)($row[4] ?? 0),
                'ballistic_skill' => (int)($row[5] ?? 0),
                'strength' => (int)($row[6] ?? 0),
                'toughness' => (int)($row[7] ?? 0),
                'agility' => (int)($row[8] ?? 0),
                'intelligence' => (int)($row[9] ?? 0),
                'willpower' => (int)($row[10] ?? 0),
                'fellowship' => (int)($row[11] ?? 0),
                'attacks' => (int)($row[12] ?? 0),
                'wounds' => (int)($row[13] ?? 0),
                'strength_bonus' => (int)($row[14] ?? 0),
                'toughness_bonus' => (int)($row[15] ?? 0),
                'movement' => (int)($row[16] ?? 0),
                'magic' => (int)($row[17] ?? 0),
                'insanity_points' => (int)($row[18] ?? 0),
                'fate_points' => (int)($row[19] ?? 0),
                'available_skills' => $row[20] ?? '',
                'available_talents' => $row[21] ?? '',
                'equipment' => $row[22] ?? '',
                'initial_professions' => $row[23] ?? '',
                'output_professions' => $row[24] ?? '',
                'is_advanced' => $this->normalizeBool($row[25] ?? false),
                'is_main' => $this->normalizeBool($row[26] ?? false),
            ];
        }, $rows);
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
}
