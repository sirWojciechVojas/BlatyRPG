<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

final class LinkCampaignsToRpgCatalog extends Migration
{
    public static function schemaContract(): array
    {
        return [
            'rpg_system_id' => [
                'target' => 'rpg_systems',
                'index' => 'idx_campaigns_rpg_system',
                'foreignKey' => 'fk_campaigns_rpg_system',
            ],
            'rpg_universe_id' => [
                'target' => 'rpg_universes',
                'index' => 'idx_campaigns_rpg_universe',
                'foreignKey' => 'fk_campaigns_rpg_universe',
            ],
        ];
    }

    public function up()
    {
        $this->assertRequiredTables();
        $this->addColumns();
        $this->backfillSelections();
        foreach (self::schemaContract() as $column => $definition) {
            $this->ensureIndex($column, $definition['index']);
            $this->ensureForeignKey($column, $definition);
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('campaigns')) {
            return;
        }
        foreach (array_reverse(self::schemaContract()) as $definition) {
            $this->dropForeignKey($definition['foreignKey']);
            $this->dropIndex($definition['index']);
        }
        foreach (array_keys(self::schemaContract()) as $column) {
            if ($this->db->fieldExists($column, 'campaigns')) {
                $this->forge->dropColumn('campaigns', $column);
            }
        }
    }

    private function assertRequiredTables(): void
    {
        foreach ([
            'campaigns',
            'rpg_systems',
            'rpg_universes',
            'rpg_system_universes',
        ] as $table) {
            if (!$this->db->tableExists($table)) {
                throw new RuntimeException("Required table {$table} is missing.");
            }
        }
    }

    private function addColumns(): void
    {
        if (!$this->db->fieldExists('rpg_system_id', 'campaigns')) {
            $this->forge->addColumn('campaigns', [
                'rpg_system_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                    'null' => true, 'after' => 'system_type',
                ],
            ]);
        }
        if (!$this->db->fieldExists('rpg_universe_id', 'campaigns')) {
            $this->forge->addColumn('campaigns', [
                'rpg_universe_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                    'null' => true, 'after' => 'rpg_system_id',
                ],
            ]);
        }
    }

    private function backfillSelections(): void
    {
        $campaigns = $this->table('campaigns');
        $systems = $this->table('rpg_systems');
        $pairs = $this->table('rpg_system_universes');
        $this->db->query(
            "UPDATE {$campaigns} campaign JOIN {$systems} system "
            . 'ON system.code = campaign.system_type '
            . 'SET campaign.rpg_system_id = system.id '
            . 'WHERE campaign.rpg_system_id IS NULL'
        );
        $this->db->query(
            "UPDATE {$campaigns} campaign JOIN ("
            . 'SELECT system_id, MIN(universe_id) AS universe_id '
            . "FROM {$pairs} WHERE is_active = 1 GROUP BY system_id "
            . 'HAVING COUNT(*) = 1) choice '
            . 'ON choice.system_id = campaign.rpg_system_id '
            . 'SET campaign.rpg_universe_id = choice.universe_id '
            . 'WHERE campaign.rpg_universe_id IS NULL'
        );
    }

    private function ensureIndex(string $column, string $name): void
    {
        if (isset($this->db->getIndexData(
            $this->db->prefixTable('campaigns')
        )[$name])) {
            return;
        }
        $this->db->query(
            "ALTER TABLE {$this->table('campaigns')} ADD INDEX {$name} ({$column})"
        );
    }

    private function ensureForeignKey(string $column, array $definition): void
    {
        if (isset($this->db->getForeignKeyData(
            $this->db->prefixTable('campaigns')
        )[$definition['foreignKey']])) {
            return;
        }
        $target = $this->table($definition['target']);
        $this->db->query(
            "ALTER TABLE {$this->table('campaigns')} ADD CONSTRAINT "
            . "{$definition['foreignKey']} FOREIGN KEY ({$column}) "
            . "REFERENCES {$target} (id) ON DELETE SET NULL ON UPDATE CASCADE"
        );
    }

    private function dropForeignKey(string $name): void
    {
        if (!isset($this->db->getForeignKeyData(
            $this->db->prefixTable('campaigns')
        )[$name])) {
            return;
        }
        $this->db->query(
            "ALTER TABLE {$this->table('campaigns')} DROP FOREIGN KEY {$name}"
        );
    }

    private function dropIndex(string $name): void
    {
        if (!isset($this->db->getIndexData(
            $this->db->prefixTable('campaigns')
        )[$name])) {
            return;
        }
        $this->db->query("ALTER TABLE {$this->table('campaigns')} DROP INDEX {$name}");
    }

    private function table(string $name): string
    {
        return (string) $this->db->escapeIdentifiers($this->db->prefixTable($name));
    }
}
