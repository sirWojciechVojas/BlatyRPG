<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateWfrpContentTables extends Migration
{
    public function up()
    {
        // Combat actions
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'description' => ['type' => 'TEXT', 'null' => true],
            'action_group'=> ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'null' => true],
            'action_type' => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('combat_actions', true);

        // Weapon traits
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('weapon_traits', true);

        // Insanities
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('insanities', true);

        // Item classes
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('item_classes', true);

        // Items
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'      => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'item_class_id'  => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 150],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'slot'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'price'          => ['type' => 'INT', 'null' => true],
            'availability'   => ['type' => 'INT', 'null' => true],
            'code'           => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'metadata'       => ['type' => 'JSON', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_class_id', 'item_classes', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('items', true);

        // Item bundles
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'bundle_type' => ['type' => 'VARCHAR', 'constraint' => 20],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('item_bundles', true);

        // Item bundle items (pivot)
        $this->forge->addField([
            'bundle_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'item_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'quantity'  => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'default' => 1],
        ]);
        $this->forge->addKey(['bundle_id', 'item_id'], true);
        $this->forge->addForeignKey('bundle_id', 'item_bundles', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('item_id', 'items', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('item_bundle_items', true);

        // Magic paths
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            'metadata'    => ['type' => 'JSON', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('magic_paths', true);

        // Magic path -> spells pivot
        $this->forge->addField([
            'path_id'        => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'spell_legacy_id'=> ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'metadata'       => ['type' => 'JSON', 'null' => true],
        ]);
        $this->forge->addKey(['path_id', 'spell_legacy_id'], true);
        $this->forge->addForeignKey('path_id', 'magic_paths', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('magic_path_spells', true);
    }

    public function down()
    {
        $this->forge->dropTable('magic_path_spells', true);
        $this->forge->dropTable('magic_paths', true);
        $this->forge->dropTable('item_bundle_items', true);
        $this->forge->dropTable('item_bundles', true);
        $this->forge->dropTable('items', true);
        $this->forge->dropTable('item_classes', true);
        $this->forge->dropTable('insanities', true);
        $this->forge->dropTable('weapon_traits', true);
        $this->forge->dropTable('combat_actions', true);
    }
}
