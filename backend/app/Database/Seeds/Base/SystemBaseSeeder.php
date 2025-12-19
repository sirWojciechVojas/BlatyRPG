<?php

namespace App\Database\Seeds\Base;

use CodeIgniter\Database\BaseBuilder;
use CodeIgniter\Database\ConnectionInterface;
use CodeIgniter\Database\Seeder;

/**
 * Bazowa klasa dla seederow powiazanych z konkretnym systemem RPG (system_id po code).
 */
abstract class SystemBaseSeeder extends Seeder
{
    protected string $systemCode;

    protected function resolveSystemId(ConnectionInterface $db): ?int
    {
        $system = $db->table('rpg_systems')->where('code', $this->systemCode)->get()->getRow();
        if (!$system) {
            echo "Nie znaleziono systemu {$this->systemCode}. Uruchom RpgInitializationSeeder.\n";
            return null;
        }

        return (int)$system->id;
    }

    protected function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Importuje wiersze, pomijajac duplikaty okreslone przez callback $existsChecker.
     *
     * @param ConnectionInterface $db
     * @param string $table nazwa tabeli
     * @param array $rows dane z Data
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
