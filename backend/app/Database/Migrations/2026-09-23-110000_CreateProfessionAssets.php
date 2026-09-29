<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Two administrator-managed, full-body illustrations per profession. */
class CreateProfessionAssets extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('profession_assets')) return;
        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'auto_increment' => true,
            ],
            'profession_id' => [
                'type' => 'INT', 'constraint' => 11, 'unsigned' => true,
            ],
            'slot' => ['type' => 'VARCHAR', 'constraint' => 16],
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'width' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'height' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_by_user_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['profession_id', 'slot']);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addKey('created_by_user_id');
        $this->forge->addForeignKey(
            'profession_id', 'professions', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL'
        );
        $this->forge->createTable('profession_assets');
    }

    public function down()
    {
        $this->forge->dropTable('profession_assets', true);
    }
}
