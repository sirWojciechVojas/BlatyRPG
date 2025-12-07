<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateRealRpgTables extends Migration
{
    public function up()
    {
        // --- 1. USERS ---
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'username'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'password_hash' => ['type' => 'VARCHAR', 'constraint' => 255],
            'role'          => ['type' => 'ENUM', 'constraint' => ['user', 'gm', 'admin'], 'default' => 'user'],
            'avatar_url'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('email');
        $this->forge->addUniqueKey('username');
        $this->forge->createTable('users', true);

        // --- 2. CAMPAIGNS ---
        $this->forge->addField([
            'id'             => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'game_master_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'name'           => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'    => ['type' => 'TEXT', 'null' => true],
            'system_type'    => ['type' => 'VARCHAR', 'constraint' => 50, 'default' => 'wfrp2ed'],
            'is_active'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('game_master_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('campaigns', true);

        // --- 3. CHARACTERS (Pełna struktura z Twojego SQL) ---
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'user_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            
            // Podstawowe
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'race'        => ['type' => 'VARCHAR', 'constraint' => 50],
            'profession'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'age'         => ['type' => 'INT', 'constraint' => 5, 'null' => true],
            
            // Wygląd i Opis
            'height'        => ['type' => 'INT', 'constraint' => 5, 'null' => true, 'comment' => 'w cm'],
            'weight'        => ['type' => 'INT', 'constraint' => 5, 'null' => true, 'comment' => 'w kg'],
            'hair'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'eyes'          => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'special_signs' => ['type' => 'TEXT', 'null' => true],
            'personality'   => ['type' => 'TEXT', 'null' => true],
            'history'       => ['type' => 'TEXT', 'null' => true],
            'notes'         => ['type' => 'TEXT', 'null' => true],

            // Statystyki (JSON jest lepszy niż 50 kolumn!)
            'stats_json'    => ['type' => 'JSON', 'null' => true], // Siła, Zręczność itp.
            'skills_json'   => ['type' => 'JSON', 'null' => true], // Umiejętności
            'talents_json'  => ['type' => 'JSON', 'null' => true], // Zdolności
            'spells_json'   => ['type' => 'JSON', 'null' => true], // Zaklęcia

            // Ekwipunek i Pieniądze
            'money_gc'      => ['type' => 'INT', 'default' => 0], // Złote korony
            'money_ss'      => ['type' => 'INT', 'default' => 0], // Srebrne szylingi
            'money_bp'      => ['type' => 'INT', 'default' => 0], // Brązowe pensy

            // Mechanika
            'current_wounds'     => ['type' => 'INT', 'default' => 10],
            'max_wounds'         => ['type' => 'INT', 'default' => 10],
            'experience_current' => ['type' => 'INT', 'default' => 0],
            'experience_total'   => ['type' => 'INT', 'default' => 0],
            'is_npc'             => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'avatar_path'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],

            // Systemowe
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('characters', true);
    }

    public function down()
    {
        // Usuwamy w odwrotnej kolejności (najpierw dzieci, potem rodzice)
        $this->forge->dropTable('characters', true);
        $this->forge->dropTable('campaigns', true);
        $this->forge->dropTable('users', true);
    }
}