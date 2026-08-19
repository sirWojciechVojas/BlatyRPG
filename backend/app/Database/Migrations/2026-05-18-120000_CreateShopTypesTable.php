<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShopTypesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'slug' => ['type' => 'VARCHAR', 'constraint' => 128],
            'name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'category' => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['slug']);
        $this->forge->addKey(['is_active', 'sort_order']);
        $this->forge->addKey(['category', 'sort_order']);
        $this->forge->createTable('shop_types', true);
    }

    public function down()
    {
        $this->forge->dropTable('shop_types', true);
    }
}
