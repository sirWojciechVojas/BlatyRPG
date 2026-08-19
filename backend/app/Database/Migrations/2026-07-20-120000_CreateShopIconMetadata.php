<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShopIconMetadata extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'icon_class' => ['type' => 'VARCHAR', 'constraint' => 5],
            'name' => ['type' => 'VARCHAR', 'constraint' => 160],
            'source_name' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'image_path' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'description' => ['type' => 'TEXT', 'null' => true],
            'special_marks' => ['type' => 'TEXT', 'null' => true],
            'type_keys_json' => ['type' => 'JSON', 'null' => true],
            'subtype_keys_json' => ['type' => 'JSON', 'null' => true],
            'item_classes_json' => ['type' => 'JSON', 'null' => true],
            'item_genres_json' => ['type' => 'JSON', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['campaign_id', 'icon_class']);
        $this->forge->addKey(['campaign_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('shop_icon_metadata', true);
    }

    public function down()
    {
        $this->forge->dropTable('shop_icon_metadata', true);
    }
}
