<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

class HardenUserRolesAndCharacterOwnership extends Migration
{
    public static function schemaContract(): array
    {
        return [
            'roles' => ['player', 'gm', 'admin'],
            'legacyRole' => 'user',
            'relations' => [
                'campaign_id' => [
                    'targetTable' => 'campaigns',
                    'targetColumn' => 'id',
                    'index' => 'idx_characters_campaign_id',
                    'constraint' => 'fk_characters_campaign_vtt',
                    'onUpdate' => 'CASCADE',
                    'onDelete' => 'CASCADE',
                ],
                'user_id' => [
                    'targetTable' => 'users',
                    'targetColumn' => 'id',
                    'index' => 'idx_characters_user_id',
                    'constraint' => 'fk_characters_owner_vtt',
                    'onUpdate' => 'CASCADE',
                    'onDelete' => 'SET NULL',
                ],
            ],
        ];
    }

    public function up()
    {
        $this->assertMySql();
        $this->assertRequiredSchema();
        $contract = self::schemaContract();
        foreach ($contract['relations'] as $column => $relation) {
            $this->assertNoOrphans($column, $relation);
            $this->assertCompatibleRelation($column, $relation);
        }

        $this->normalizeAndHardenRoles($contract['roles']);
        foreach ($contract['relations'] as $column => $relation) {
            $this->ensureIndex($column, $relation['index']);
            $this->ensureRelation($column, $relation);
        }
    }

    public function down()
    {
        $this->assertMySql();
        if ($this->db->tableExists('characters')) {
            foreach (array_reverse(self::schemaContract()['relations'])
                as $relation) {
                $this->dropRelation($relation['constraint']);
                $this->dropIndex($relation['index']);
            }
        }
        if ($this->db->tableExists('users')
            && $this->db->fieldExists('role', 'users')) {
            $this->setRoleEnum(['user', 'player', 'gm', 'admin']);
        }
    }

    private function assertMySql(): void
    {
        if (strtolower((string) $this->db->DBDriver) !== 'mysqli') {
            throw new RuntimeException(
                'Character ownership hardening requires the MariaDB/MySQL driver.'
            );
        }
    }

    private function assertRequiredSchema(): void
    {
        foreach (['users', 'campaigns', 'characters'] as $table) {
            if (!$this->db->tableExists($table)) {
                throw new RuntimeException("Required table {$table} is missing.");
            }
        }
        foreach (['campaign_id', 'user_id'] as $column) {
            if (!$this->db->fieldExists($column, 'characters')) {
                throw new RuntimeException("characters.{$column} is missing.");
            }
        }
        if (!$this->db->fieldExists('role', 'users')) {
            throw new RuntimeException('users.role is missing.');
        }
        foreach ($this->db->getFieldData('characters') as $field) {
            if ($field->name === 'user_id' && !$field->nullable) {
                throw new RuntimeException(
                    'characters.user_id must be nullable for ON DELETE SET NULL.'
                );
            }
        }
    }

    private function assertNoOrphans(string $column, array $relation): void
    {
        $characters = $this->identifier($this->table('characters'));
        $target = $this->identifier($this->table($relation['targetTable']));
        $columnName = $this->identifier($column);
        $targetColumn = $this->identifier($relation['targetColumn']);
        $row = $this->db->query(
            "SELECT COUNT(*) AS orphan_count FROM {$characters} c "
            . "LEFT JOIN {$target} target ON target.{$targetColumn} = c.{$columnName} "
            . "WHERE c.{$columnName} IS NOT NULL AND target.{$targetColumn} IS NULL"
        )->getRowArray();
        if ((int) ($row['orphan_count'] ?? 0) > 0) {
            throw new RuntimeException(
                "Cannot constrain characters.{$column}: orphan references exist."
            );
        }
    }

    private function normalizeAndHardenRoles(array $roles): void
    {
        $users = $this->identifier($this->table('users'));
        $allowed = implode(', ', array_map([$this->db, 'escape'], $roles));
        $this->db->query(
            "UPDATE {$users} SET role = 'player' "
            . "WHERE role IS NULL OR role NOT IN ({$allowed})"
        );
        $this->setRoleEnum($roles);
    }

    private function setRoleEnum(array $roles): void
    {
        $users = $this->identifier($this->table('users'));
        $allowed = implode(', ', array_map([$this->db, 'escape'], $roles));
        $this->db->query(
            "ALTER TABLE {$users} MODIFY COLUMN role ENUM({$allowed}) "
            . "NOT NULL DEFAULT 'player'"
        );
    }

    private function assertCompatibleRelation(
        string $column,
        array $expected
    ): void {
        foreach ($this->relationsFor($column) as $relation) {
            if (!$this->relationMatches($relation, $expected)) {
                throw new RuntimeException(
                    "characters.{$column} has an incompatible foreign key."
                );
            }
        }
    }

    private function ensureIndex(string $column, string $name): void
    {
        foreach ($this->db->getIndexData($this->table('characters')) as $index) {
            if (($index->fields[0] ?? null) === $column) {
                return;
            }
        }
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->table('characters'))
            . ' ADD INDEX ' . $this->identifier($name)
            . ' (' . $this->identifier($column) . ')'
        );
    }

    private function ensureRelation(string $column, array $expected): void
    {
        foreach ($this->relationsFor($column) as $relation) {
            if ($this->relationMatches($relation, $expected)) {
                return;
            }
        }
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->table('characters'))
            . ' ADD CONSTRAINT ' . $this->identifier($expected['constraint'])
            . ' FOREIGN KEY (' . $this->identifier($column) . ') REFERENCES '
            . $this->identifier($this->table($expected['targetTable']))
            . ' (' . $this->identifier($expected['targetColumn']) . ')'
            . ' ON UPDATE ' . $expected['onUpdate']
            . ' ON DELETE ' . $expected['onDelete']
        );
    }

    private function relationsFor(string $column): array
    {
        return array_filter(
            $this->db->getForeignKeyData($this->table('characters')),
            static function ($relation) use ($column): bool {
                return $relation->column_name === [$column];
            }
        );
    }

    private function relationMatches($relation, array $expected): bool
    {
        return $relation->foreign_table_name === $this->table($expected['targetTable'])
            && $relation->foreign_column_name === [$expected['targetColumn']]
            && strtoupper($relation->on_update) === $expected['onUpdate']
            && strtoupper($relation->on_delete) === $expected['onDelete'];
    }

    private function dropRelation(string $name): void
    {
        $relations = $this->db->getForeignKeyData($this->table('characters'));
        if (!isset($relations[$name])) {
            return;
        }
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->table('characters'))
            . ' DROP FOREIGN KEY ' . $this->identifier($name)
        );
    }

    private function dropIndex(string $name): void
    {
        $indexes = $this->db->getIndexData($this->table('characters'));
        if (!isset($indexes[$name])) {
            return;
        }
        $this->db->query(
            'ALTER TABLE ' . $this->identifier($this->table('characters'))
            . ' DROP INDEX ' . $this->identifier($name)
        );
    }

    private function table(string $name): string
    {
        return $this->db->prefixTable($name);
    }

    private function identifier(string $name): string
    {
        return (string) $this->db->escapeIdentifiers($name);
    }
}
