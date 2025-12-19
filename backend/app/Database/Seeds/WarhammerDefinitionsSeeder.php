<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;

class WarhammerDefinitionsSeeder extends WfrpBaseSeeder
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

        $skills = $this->importSkills($db, $systemId);
        $talents = $this->importTalents($db, $systemId);
        $attributes = $this->importAttributes($db, $systemId);

        echo "Zaimportowano $skills umiejetności, $talents zdolności, $attributes cech.\n";
    }

    private function importSkills($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getSkills', 'Pomijam umiejetności (brak getSkills w WfrpData).');

        return $this->importRows(
            $db,
            'game_definitions',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'category'    => 'umiejetnosc',
                    'name'        => $name,
                    'description' => isset($row[4]) ? $row[4] : '',
                    'metadata'    => json_encode([
                        'typ'       => $row[2] ?? 'Podstawowa',
                        'cecha'     => $row[3] ?? '',
                        'powiazane' => $row[5] ?? '',
                        'koszt_xp'  => $row[6] ?? 0,
                        'mechanika' => $row[7] ?? '',
                    ]),
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            },
            function ($builder, array $data) {
                return $builder->where('system_id', $data['system_id'])
                    ->where('category', $data['category'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }

    private function importTalents($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getTalents', 'Pomijam zdolności (brak getTalents w WfrpData).');

        return $this->importRows(
            $db,
            'game_definitions',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'category'    => 'zdolnosc',
                    'name'        => $name,
                    'description' => $row[2] ?? '',
                    'metadata'    => json_encode([
                        'efekt'       => $row[3] ?? '',
                        'modyfikator' => $row[4] ?? '',
                        'talent'      => $row[5] ?? '',
                    ]),
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            },
            function ($builder, array $data) {
                return $builder->where('system_id', $data['system_id'])
                    ->where('category', $data['category'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }

    private function importAttributes($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getAttributes', 'Pomijam cechy (brak getAttributes w WfrpData).');

        return $this->importRows(
            $db,
            'game_definitions',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[2] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'category'    => 'attribute',
                    'name'        => $name,
                    'description' => $row[4] ?? '',
                    'metadata'    => json_encode([
                        'legacy_id' => $row[0] ?? null,
                        'code'      => $row[1] ?? '',
                        'short'     => $row[3] ?? '',
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            },
            function ($builder, array $data) {
                return $builder->where('system_id', $data['system_id'])
                    ->where('category', $data['category'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }
}
