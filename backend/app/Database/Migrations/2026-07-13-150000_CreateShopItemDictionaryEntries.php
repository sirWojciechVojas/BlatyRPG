<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShopItemDictionaryEntries extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'group_code' => ['type' => 'VARCHAR', 'constraint' => 32],
            'code' => ['type' => 'VARCHAR', 'constraint' => 64],
            'label_pl' => ['type' => 'VARCHAR', 'constraint' => 255],
            'label_en' => ['type' => 'VARCHAR', 'constraint' => 255],
            'applies_to_json' => ['type' => 'JSON', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'group_code', 'code']);
        $this->forge->addKey(['campaign_id', 'group_code', 'is_active', 'sort_order']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_item_dictionary_entries', true);
    }

    public function down()
    {
        $this->forge->dropTable('shop_item_dictionary_entries', true);
    }
}
