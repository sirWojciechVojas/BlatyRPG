<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnhanceCharacterStructure extends Migration
{
    public function up()
    {
        // 1. Historia karier postaci
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'character_id'  => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'profession_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'is_current'    => ['type' => 'BOOLEAN', 'default' => false],
            'is_finished'   => ['type' => 'BOOLEAN', 'default' => false],
            'started_at'    => ['type' => 'DATETIME', 'null' => true],
            'finished_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('profession_id', 'professions', 'id', 'CASCADE', 'CASCADE');
        if (!$this->db->tableExists('character_professions')) {
            $this->forge->createTable('character_professions');
        }

        // 2. Rozwinięcia cech
        $this->forge->addField([
            'id'                 => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'character_id'       => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'attribute_name'     => ['type' => 'VARCHAR', 'constraint' => 50],
            'advances_purchased' => ['type' => 'INT', 'constraint' => 3, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        if (!$this->db->tableExists('character_attribute_advances')) {
            $this->forge->createTable('character_attribute_advances');
        }

        // 3. Odblokowane przez MG
        $this->forge->addField([
            'id'           => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'character_id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true],
            'skill_id'     => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'talent_id'    => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'null' => true],
            'reason'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addForeignKey('character_id', 'characters', 'id', 'CASCADE', 'CASCADE');
        if (!$this->db->tableExists('character_custom_unlocks')) {
            $this->forge->createTable('character_custom_unlocks');
        }

        // 4. Usunięcie starej kolumny current_profession_id (jeśli istnieje)
        try {
            $this->forge->dropForeignKey('characters', 'characters_current_profession_id_foreign');
        } catch (\Throwable $e) {
            // FK może nie istnieć w aktualnej strukturze – ignorujemy
        }

        if ($this->db->fieldExists('current_profession_id', 'characters')) {
            $this->forge->dropColumn('characters', 'current_profession_id');
        }
    }

    public function down()
    {
        $this->forge->dropTable('character_custom_unlocks');
        $this->forge->dropTable('character_attribute_advances');
        $this->forge->dropTable('character_professions');
        // Przywracanie kolumny current_profession_id pominięte
    }
}
