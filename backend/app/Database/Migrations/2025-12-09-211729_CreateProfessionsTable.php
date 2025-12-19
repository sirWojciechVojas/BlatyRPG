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
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => '255',
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'details' => [
                'type' => 'TEXT', // Miejsce na dodatkowe zasady, uwagi mechaniczne
                'null' => true,
            ],
            // Statystyki Główne (Main Profile)
            'weapon_skill' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'ballistic_skill' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'strength' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'toughness' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'agility' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'intelligence' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'willpower' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'fellowship' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            // Statystyki Drugorzędne (Secondary Profile)
            'attacks' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'wounds' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'strength_bonus' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'toughness_bonus' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'movement' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'magic' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'insanity_points' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            'fate_points' => [
                'type'       => 'INT',
                'constraint' => 11,
                'default'    => 0,
            ],
            // Listy i powiązania (przechowywane jako tekst dla elastyczności parsera)
            'available_skills' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'available_talents' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'equipment' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'initial_professions' => [
                'type' => 'TEXT', // Ścieżki wejścia
                'null' => true,
            ],
            'output_professions' => [
                'type' => 'TEXT', // Ścieżki wyjścia
                'null' => true,
            ],
            // Flagi
            'is_advanced' => [
                'type'    => 'BOOLEAN',
                'default' => false,
            ],
            'is_main' => [
                'type'    => 'BOOLEAN',
                'default' => true, // Np. czy pochodzi z głównego podręcznika
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
        $this->forge->createTable('professions');
    }

    public function down()
    {
        $this->forge->dropTable('professions');
    }
}