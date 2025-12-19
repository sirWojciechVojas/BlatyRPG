<?php

namespace App\Database\Seeds\Base;

use App\Database\Seeds\Data\WfrpData;
use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\Seeder;

/**
 * Wspolne helpery dla seederow WFRP bazujacych na WfrpData.
 */
abstract class WfrpBaseSeeder extends Seeder
{
    protected string $systemCode = 'wfrp2ed';

    protected function ensureWfrpDataAvailable(): bool
    {
        if (!class_exists(WfrpData::class)) {
            echo "Brak klasy WfrpData - uruchom generator danych.\n";
            return false;
        }

        return true;
    }

    protected function resolveSystemId(ConnectionInterface $db): ?int
    {
        $system = $db->table('rpg_systems')->where('code', $this->systemCode)->get()->getRow();
        if (!$system) {
            echo "Nie znaleziono systemu {$this->systemCode}. Uruchom RpgInitializationSeeder.\n";
            return null;
        }

        return $system->id;
    }

    protected function getWfrpRows(string $method, string $missingMessage): array
    {
        if (!method_exists(WfrpData::class, $method)) {
            echo $missingMessage . "\n";
            return [];
        }

        $rows = call_user_func([WfrpData::class, $method]);
        return is_array($rows) ? $rows : [];
    }

    /**
     * Importuje wiersze, pomijajac duplikaty okreslone przez callback $existsChecker.
     *
     * @param ConnectionInterface $db
     * @param string $table nazwa tabeli
     * @param array $rows dane z WfrpData
     * @param callable $mapper fn(array $row): ?array
     * @param callable $existsChecker fn(BaseBuilder $builder, array $data): bool
     */
    protected function importRows(ConnectionInterface $db, string $table, array $rows, callable $mapper, callable $existsChecker): int
    {
        $builder = $db->table($table);
        $count = 0;

        foreach ($rows as $row) {
            $data = $mapper($row);
            if (!$data || empty($data['name'] ?? null)) {
                continue;
            }

            if ($existsChecker($builder, $data)) {
                continue;
            }

            $builder->insert($data);
            $count++;
        }

        return $count;
    }
}
