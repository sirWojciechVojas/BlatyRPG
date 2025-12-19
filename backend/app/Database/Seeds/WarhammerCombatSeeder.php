<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;

class WarhammerCombatSeeder extends WfrpBaseSeeder
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

        $actions = $this->importCombatActions($db, $systemId);
        $traits = $this->importWeaponTraits($db, $systemId);

        echo "Zaimportowano $actions akcji bojowych oraz $traits cech broni.\n";
    }

    private function importCombatActions($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getCombatActions', 'Pomijam akcje bojowe (brak getCombatActions w WfrpData).');

        return $this->importRows(
            $db,
            'combat_actions',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[2] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'    => $systemId,
                    'name'         => $name,
                    'description'  => $row[3] ?? '',
                    'action_group' => $row[1] ?? null,
                    'action_type'  => $row[4] ?? null,
                    'metadata'     => json_encode([
                        'legacy_id' => $row[0] ?? null,
                        'raw'       => $row,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'   => date('Y-m-d H:i:s'),
                ];
            },
            function ($builder, array $data) {
                return $builder->where('system_id', $data['system_id'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }

    private function importWeaponTraits($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getWeaponTraits', 'Pomijam cechy broni (brak getWeaponTraits w WfrpData).');

        return $this->importRows(
            $db,
            'weapon_traits',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'name'        => $name,
                    'description' => $row[2] ?? '',
                    'metadata'    => json_encode([
                        'legacy_id' => $row[0] ?? null,
                        'raw'       => $row,
                    ], JSON_UNESCAPED_UNICODE),
                    'created_at'  => date('Y-m-d H:i:s'),
                ];
            },
            function ($builder, array $data) {
                return $builder->where('system_id', $data['system_id'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }
}
