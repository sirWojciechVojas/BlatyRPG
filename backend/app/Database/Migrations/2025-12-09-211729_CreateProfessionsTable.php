<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateProfessionsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'system_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'details' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'is_advanced' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'is_main' => [
                'type'    => 'BOOLEAN',
                'default' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('system_id');
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('professions');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'profession_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'attribute_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
            ],
            'attribute_group' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'value' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('profession_id');
        $this->forge->addForeignKey('profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('profession_attributes');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'profession_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'definition_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'metadata' => [
                'type' => 'JSON',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('profession_id');
        $this->forge->addKey('definition_id');
        $this->forge->addForeignKey('profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('definition_id', 'game_definitions', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('profession_definitions');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'profession_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'related_profession_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'relation_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('profession_id');
        $this->forge->addKey('related_profession_id');
        $this->forge->addForeignKey('profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('related_profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('profession_paths');

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'profession_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'definition_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
            ],
            'item_name' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'quantity' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 1,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('profession_id');
        $this->forge->addKey('definition_id');
        $this->forge->addForeignKey('profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('definition_id', 'game_definitions', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('profession_equipment');
    }

    public function down()
    {
        $this->forge->dropTable('profession_equipment', true);
        $this->forge->dropTable('profession_paths', true);
        $this->forge->dropTable('profession_definitions', true);
        $this->forge->dropTable('profession_attributes', true);
        $this->forge->dropTable('professions', true);
    }
}
