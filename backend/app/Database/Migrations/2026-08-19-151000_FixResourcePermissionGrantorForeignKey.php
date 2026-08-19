<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixResourcePermissionGrantorForeignKey extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('resource_permissions')
            || strtolower((string) $this->db->DBDriver) !== 'mysqli') {
            return;
        }

        $this->repairGrantorRelation();
    }

    public function down()
    {
        // The integrity correction is intentionally not reverted.
    }


    private function repairGrantorRelation(): void
    {
        $relations = $this->relations();
        $keptName = null;
        foreach ($relations as $relation) {
            if ($this->hasCorrectRules($relation)) {
                $keptName = (string) $relation['CONSTRAINT_NAME'];
                break;
            }
        }

        if ($keptName === null) {
            $keptName = $this->availableConstraintName($relations);
            $this->addCorrectRelation($keptName);
        }

        foreach ($relations as $relation) {
            $name = (string) $relation['CONSTRAINT_NAME'];
            if ($name !== $keptName) {
                $this->dropRelation($name);
            }
        }
    }

    private function relations(): array
    {
        return $this->db->query(
            'SELECT rc.CONSTRAINT_NAME, rc.UPDATE_RULE, rc.DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS rc
             INNER JOIN information_schema.KEY_COLUMN_USAGE kcu
               ON kcu.CONSTRAINT_SCHEMA = rc.CONSTRAINT_SCHEMA
              AND kcu.TABLE_NAME = rc.TABLE_NAME
              AND kcu.CONSTRAINT_NAME = rc.CONSTRAINT_NAME
             WHERE rc.CONSTRAINT_SCHEMA = DATABASE()
               AND rc.TABLE_NAME = ?
               AND kcu.COLUMN_NAME = ?
               AND kcu.REFERENCED_TABLE_NAME = ?
               AND kcu.REFERENCED_COLUMN_NAME = ?',
            [$this->tableName(), 'granted_by_user_id', $this->usersTableName(), 'id']
        )->getResultArray();
    }

    private function addCorrectRelation(string $name): void
    {
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->tableName())
            . ' ADD CONSTRAINT ' . $this->identifier($name)
            . ' FOREIGN KEY (' . $this->identifier('granted_by_user_id') . ')'
            . ' REFERENCES ' . $this->identifier($this->usersTableName())
            . ' (' . $this->identifier('id') . ')'
            . ' ON UPDATE CASCADE ON DELETE SET NULL'
        );
    }

    private function dropRelation(string $name): void
    {
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->tableName())
            . ' DROP FOREIGN KEY ' . $this->identifier($name)
        );
    }

    private function availableConstraintName(array $relations): string
    {
        $taken = [];
        foreach ($relations as $relation) {
            $taken[(string) $relation['CONSTRAINT_NAME']] = true;
        }
        $base = substr($this->tableName(), 0, 40)
            . '_grantor_' . substr(sha1($this->tableName()), 0, 8);
        $name = $base;
        $suffix = 2;
        while (isset($taken[$name])) {
            $name = substr($base, 0, 61) . '_' . $suffix;
            $suffix++;
        }
        return $name;
    }

    private function hasCorrectRules(array $relation): bool
    {
        return strtoupper((string) $relation['UPDATE_RULE']) === 'CASCADE'
            && strtoupper((string) $relation['DELETE_RULE']) === 'SET NULL';
    }

    private function tableName(): string
    {
        return $this->db->prefixTable('resource_permissions');
    }

    private function usersTableName(): string
    {
        return $this->db->prefixTable('users');
    }

    private function identifier(string $name): string
    {
        return (string) $this->db->escapeIdentifiers($name);
    }
}
