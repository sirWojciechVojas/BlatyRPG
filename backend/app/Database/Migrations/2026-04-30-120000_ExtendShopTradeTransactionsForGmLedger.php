<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendShopTradeTransactionsForGmLedger extends Migration
{
    public function up()
    {
        $table = 'shop_trade_transactions';
        $fields = [
            'updated_at' => ['type' => 'DATETIME', 'null' => true, 'after' => 'created_at'],
            'shop_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'shop_id'],
            'actor_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'actor_user_id'],
            'actor_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'actor_id'],
            'seller_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'shop_name'],
            'seller_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'seller_id'],
            'buyer_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'seller_name'],
            'buyer_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'buyer_id'],
            'item_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'error_code'],
            'item_template_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'item_id'],
            'item_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'item_template_id'],
            'quantity' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'after' => 'item_name'],
            'base_price' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'after' => 'quantity'],
            'final_price' => ['type' => 'INT', 'constraint' => 11, 'default' => 0, 'after' => 'base_price'],
            'currency' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'brass', 'after' => 'final_price'],
            'price_modifiers_json' => ['type' => 'JSON', 'null' => true, 'after' => 'currency'],
            'conditions_snapshot_json' => ['type' => 'JSON', 'null' => true, 'after' => 'price_modifiers_json'],
            'before_snapshot_json' => ['type' => 'JSON', 'null' => true, 'after' => 'conditions_snapshot_json'],
            'after_snapshot_json' => ['type' => 'JSON', 'null' => true, 'after' => 'before_snapshot_json'],
            'parent_transaction_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'after_snapshot_json'],
            'correction_reason' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'parent_transaction_id'],
            'gm_note' => ['type' => 'TEXT', 'null' => true, 'after' => 'correction_reason'],
            'performed_by' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'gm_note'],
            'reversed_by' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'performed_by'],
            'redone_by' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'reversed_by'],
            'history_json' => ['type' => 'JSON', 'null' => true, 'after' => 'redone_by'],
        ];

        $existingFields = $this->getCurrentFields($table);

        foreach ($fields as $name => $definition) {
            if (!in_array($name, $existingFields, true)) {
                if (isset($definition['after']) && !in_array($definition['after'], $existingFields, true)) {
                    unset($definition['after']);
                }

                $this->forge->addColumn($table, [$name => $definition]);
                $existingFields[] = $name;
            }
        }

        $existingFields = $this->getCurrentFields($table);

        $this->createIndexIfMissing($table, 'idx_shop_trade_ledger_status', ['campaign_id', 'status', 'created_at'], $existingFields);
        $this->createIndexIfMissing($table, 'idx_shop_trade_ledger_shop', ['campaign_id', 'shop_id', 'created_at'], $existingFields);
        $this->createIndexIfMissing($table, 'idx_shop_trade_ledger_parent', ['campaign_id', 'parent_transaction_id'], $existingFields);
    }

    public function down()
    {
        $table = 'shop_trade_transactions';
        $this->dropIndexIfExists($table, 'idx_shop_trade_ledger_parent');
        $this->dropIndexIfExists($table, 'idx_shop_trade_ledger_shop');
        $this->dropIndexIfExists($table, 'idx_shop_trade_ledger_status');

        $fields = [
            'history_json',
            'redone_by',
            'reversed_by',
            'performed_by',
            'gm_note',
            'correction_reason',
            'parent_transaction_id',
            'after_snapshot_json',
            'before_snapshot_json',
            'conditions_snapshot_json',
            'price_modifiers_json',
            'currency',
            'final_price',
            'base_price',
            'quantity',
            'item_name',
            'item_template_id',
            'item_id',
            'buyer_name',
            'buyer_id',
            'seller_name',
            'seller_id',
            'actor_name',
            'actor_id',
            'shop_name',
            'updated_at',
        ];

        foreach ($fields as $field) {
            if ($this->fieldExists($table, $field)) {
                $this->forge->dropColumn($table, $field);
            }
        }
    }

    private function getCurrentFields(string $table): array
    {
        return array_map(static function ($field) {
            return $field->name;
        }, $this->db->getFieldData($table));
    }

    private function fieldExists(string $table, string $field): bool
    {
        return in_array($field, $this->getCurrentFields($table), true);
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns, array $existingFields): void
    {
        foreach ($columns as $column) {
            if (!in_array($column, $existingFields, true)) {
                return;
            }
        }

        if (array_key_exists($indexName, $this->db->getIndexData($table))) {
            return;
        }

        $escapedColumns = array_map(function ($column) {
            return $this->db->escapeIdentifiers($column);
        }, $columns);

        $this->db->query(sprintf(
            'CREATE INDEX %s ON %s (%s)',
            $this->db->escapeIdentifiers($indexName),
            $this->db->escapeIdentifiers($this->db->DBPrefix . $table),
            implode(', ', $escapedColumns)
        ));
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (array_key_exists($indexName, $this->db->getIndexData($table))) {
            $this->forge->dropKey($table, $indexName);
        }
    }
}
