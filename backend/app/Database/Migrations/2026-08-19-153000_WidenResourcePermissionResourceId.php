<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class WidenResourcePermissionResourceId extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('resource_permissions')
            || strtolower((string) $this->db->DBDriver) !== 'mysqli') {
            return;
        }

        $table = $this->db->prefixTable('resource_permissions');
        $column = $this->db->query(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?
               AND COLUMN_NAME = ?',
            [$table, 'resource_id']
        )->getRowArray();
        if (!$column) {
            throw new \RuntimeException('resource_permissions.resource_id is missing.');
        }
        $type = strtolower(trim((string) $column['COLUMN_TYPE']));
        if (preg_match('/^bigint(?:\([0-9]+\))? unsigned$/', $type)) {
            return;
        }

        $this->db->query(
            'ALTER TABLE ' . $this->identifier($table)
            . ' MODIFY ' . $this->identifier('resource_id')
            . ' BIGINT UNSIGNED NOT NULL'
        );
    }

    public function down()
    {
        // Narrowing could truncate valid scene IDs, so it is intentionally omitted.
    }

    private function identifier(string $name): string
    {
        return (string) $this->db->escapeIdentifiers($name);
    }
}
