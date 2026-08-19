<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendShopProfilesForMarketEngine extends Migration
{
    public function up()
    {
        $columns = [
            'market_settings_json' => ['type' => 'JSON', 'null' => true],
            'market_events_json' => ['type' => 'JSON', 'null' => true],
            'custom_presets_json' => ['type' => 'JSON', 'null' => true],
        ];
        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, 'shop_profiles')) {
                $this->forge->addColumn('shop_profiles', [$name => $definition]);
            }
        }

        if (!$this->db->tableExists('shop_profile_revisions')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'shop_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'changed_by' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
                'change_type' => ['type' => 'VARCHAR', 'constraint' => 32, 'default' => 'update'],
                'snapshot_json' => ['type' => 'JSON'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['campaign_id', 'shop_id', 'created_at']);
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('shop_id', 'shops', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shop_profile_revisions', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('shop_profile_revisions')) {
            $this->forge->dropTable('shop_profile_revisions', true);
        }
        foreach (['custom_presets_json', 'market_events_json', 'market_settings_json'] as $column) {
            if ($this->db->fieldExists($column, 'shop_profiles')) {
                $this->forge->dropColumn('shop_profiles', $column);
            }
        }
    }
}
