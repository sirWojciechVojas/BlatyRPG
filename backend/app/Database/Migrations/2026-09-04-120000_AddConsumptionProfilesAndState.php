<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddConsumptionProfilesAndState extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('consumption_profile_id', 'shop_templates')) {
            $this->forge->addColumn('shop_templates', [
                'consumption_profile_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'mechanics_mode'],
            ]);
        }
        if (!$this->db->fieldExists('consumption_mode', 'shop_item_instances')) {
            $this->forge->addColumn('shop_item_instances', [
                'consumption_mode' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'inherit', 'after' => 'template_id'],
                'consumption_profile_id' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true, 'after' => 'consumption_mode'],
                'consumption_identification' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'unknown', 'after' => 'consumption_profile_id'],
                'consumption_portions' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'consumption_identification'],
            ]);
        }
        if (!$this->db->tableExists('campaign_consumption_states')) {
            $this->forge->addField([
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'world_minute' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('campaign_id', true);
            $this->forge->createTable('campaign_consumption_states', true);
        }
        if (!$this->db->tableExists('consumption_requests')) {
            $this->forge->addField([
                'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'request_key' => ['type' => 'VARCHAR', 'constraint' => 128],
                'result_json' => ['type' => 'JSON', 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['campaign_id', 'request_key'], 'uq_consumption_request');
            $this->forge->createTable('consumption_requests', true);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('consumption_requests')) $this->forge->dropTable('consumption_requests', true);
        if ($this->db->tableExists('campaign_consumption_states')) $this->forge->dropTable('campaign_consumption_states', true);
        if ($this->db->fieldExists('consumption_profile_id', 'shop_templates')) $this->forge->dropColumn('shop_templates', 'consumption_profile_id');
        if ($this->db->fieldExists('consumption_mode', 'shop_item_instances')) {
            $this->forge->dropColumn('shop_item_instances', ['consumption_portions', 'consumption_identification', 'consumption_profile_id', 'consumption_mode']);
        }
    }
}
