<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;

class WarhammerEquipmentSeeder extends WfrpBaseSeeder
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

        $classes = $this->importItemClasses($db, $systemId);
        $items = $this->importItems($db, $systemId);
        $bgBundles = $this->importItemBundles($db, $systemId, 'bg');
        $optBundles = $this->importItemBundles($db, $systemId, 'opt');

        echo "Zaimportowano $classes klas przedmiotów, $items przedmiotów, $bgBundles pakietów bg, $optBundles pakietów punktów dostępowych.\n";
    }

    private function importItems($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getEquipment', 'Pomijam sprzęt (brak getEquipment w WfrpData).');

        return $this->importRows(
            $db,
            'items',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'    => $systemId,
                    'name'         => $name,
                    'description'  => $row[2] ?? '',
                    'slot'         => $row[6] ?? null,
                    'price'        => $row[8] ?? null,
                    'availability' => $row[9] ?? null,
                    'code'         => $row[7] ?? null,
                    'metadata'     => json_encode([
                        'legacy_id'  => $row[0] ?? null,
                        'extra'      => $row[3] ?? '',
                        'type_code'  => $row[4] ?? '',
                        'weapon_ref' => $row[5] ?? null,
                        'raw'        => $row,
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

    private function importItemBundles($db, int $systemId, string $type): int
    {
        $method = $type === 'bg' ? 'getEquipmentBg' : 'getEquipmentOpt';
        $rows = $this->getWfrpRows($method, "Pomijam pakiety ekwipunku ($type) - brak $method w WfrpData.");

        return $this->importRows(
            $db,
            'item_bundles',
            $rows,
            function (array $row) use ($systemId, $type) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'bundle_type' => $type,
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
                    ->where('bundle_type', $data['bundle_type'])
                    ->where('name', $data['name'])
                    ->countAllResults() > 0;
            }
        );
    }

    private function importItemClasses($db, int $systemId): int
    {
        $rows = $this->getWfrpRows('getItemClasses', 'Pomijam klasy przedmiotów (brak getItemClasses w WfrpData).');

        return $this->importRows(
            $db,
            'item_classes',
            $rows,
            function (array $row) use ($systemId) {
                $name = $row[1] ?? '';
                if (!$name) {
                    return null;
                }

                return [
                    'system_id'   => $systemId,
                    'code'        => $row[0] ?? null,
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
