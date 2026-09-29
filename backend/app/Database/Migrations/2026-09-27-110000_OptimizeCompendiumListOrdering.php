<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class OptimizeCompendiumListOrdering extends Migration
{
    private const INDEXES = [
        'compendium_entries' => [
            'idx_compendium_entries_active_list' => [
                'world_id',
                'status',
                'deleted_at',
                'published_version_id',
            ],
        ],
        'compendium_entry_versions' => [
            'idx_compendium_versions_list_title' => ['title', 'id'],
            'idx_compendium_versions_list_timeline' => [
                'start_ordinal',
                'title',
                'id',
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
