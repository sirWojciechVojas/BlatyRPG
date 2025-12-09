<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCharactersTable extends Migration
{
    public function up()
    {
        // 1. ZABEZPIECZENIE: Usuwamy starą tabelę, jeśli istnieje (Hard Reset dla tej tabeli)
        // Wyłączamy sprawdzanie kluczy obcych, aby uniknąć błędów, jeśli coś jest podpięte pod characters
        $this->db->query('SET FOREIGN_KEY_CHECKS = 0');
        $this->forge->dropTable('characters', true);
        $this->db->query('SET FOREIGN_KEY_CHECKS = 1');

        // 2. TWORZENIE NOWEJ STRUKTURY
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 10,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true, // Postać może nie być przypisana do usera (np. NPC)
            ],
            'campaign_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'system_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
            ],
            'universe_id' => [
                'type'       => 'INT',
                'constraint' => 10,
                'unsigned'   => true,
                'null'       => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            // To jest serce elastyczności - tu trafią statystyki z WFRP (WW, US, S, Wt...)
            'data' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'avatar_url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
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

        $this->forge->addPrimaryKey('id');
        
        // Klucze obce (zakładamy, że tabele rpg_systems/universes już istnieją z poprzednich kroków)
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('universe_id', 'rpg_universes', 'id', 'SET NULL', 'CASCADE');
        
        $this->forge->createTable('characters', true);
    }

    public function down()
    {
        $this->forge->dropTable('characters', true);
    }
}