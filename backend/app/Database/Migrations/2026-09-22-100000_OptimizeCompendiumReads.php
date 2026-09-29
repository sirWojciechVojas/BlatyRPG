<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class OptimizeCompendiumReads extends Migration
{
    private const INDEXES = [
        'compendium_entities' => [
            'idx_compendium_entities_overview' => [
                'world_id',
                'deleted_at',
                'visibility',
                'verification_status',
                'type_code',
            ],
        ],
        'compendium_user_activity' => [
            'idx_compendium_activity_overview' => [
                'campaign_id',
                'user_id',
                'is_favorite',
                'last_read_at',
            ],
        ],
    ];

    public function up()
    {
        foreach (self::INDEXES as $table => $indexes) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $existing = $this->db->getIndexData($table);
            foreach ($indexes as $name => $columns) {
                if (isset($existing[$name])) {
                    continue;
                }
                $this->db->query(sprintf(
                    'CREATE INDEX `%s` ON `%s` (`%s`)',
                    $name,
                    $this->db->prefixTable($table),
                    implode('`,`', $columns)
                ));
            }
        }
    }

    public function down()
    {
        $sqlite = strtolower((string) $this->db->DBDriver) === 'sqlite3';
        foreach (self::INDEXES as $table => $indexes) {
            if (!$this->db->tableExists($table)) {
                continue;
            }
            $existing = $this->db->getIndexData($table);
            foreach (array_keys($indexes) as $name) {
                if (!isset($existing[$name])) {
                    continue;
                }
                $this->db->query($sqlite
                    ? sprintf('DROP INDEX IF EXISTS `%s`', $name)
                    : sprintf(
                        'DROP INDEX `%s` ON `%s`',
                        $name,
                        $this->db->prefixTable($table)
                    ));
            }
        }
    }
}
