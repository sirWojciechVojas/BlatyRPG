<?php

namespace App\Database\Seeds;

use App\Database\Seeds\Base\WfrpBaseSeeder;
use App\Database\Seeds\Data\WfrpData;

class WarhammerInsanitySeeder extends WfrpBaseSeeder
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

        $rows = $this->getWfrpRows('getInsanities', 'Pomijam choroby umysłu (brak getInsanities w WfrpData).');

        $count = $this->importRows(
            $db,
            'insanities',
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

        echo "Zaimportowano $count chorób umysłu.\n";
    }
}
