<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUniversalGameSystems extends Migration
{
    public function up()
    {
        // 1. SYSTEMY RPG (Słownik mechanik)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'code'        => ['type' => 'VARCHAR', 'constraint' => 50], // np. 'wfrp2ed'
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'description' => ['type' => 'TEXT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->createTable('rpg_systems', true); // true = IF NOT EXISTS

        // 2. UNIWERSA (Settings / Worlds) - Nowa tabela
        $this->forge->addField([
            'id'                => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'default_system_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true], // Domyślny system dla świata
            'code'              => ['type' => 'VARCHAR', 'constraint' => 50], // np. 'old_world'
            'name'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'description'       => ['type' => 'TEXT', 'null' => true],
            'created_at'        => ['type' => 'DATETIME', 'null' => true],
            'updated_at'        => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('code');
        $this->forge->addForeignKey('default_system_id', 'rpg_systems', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('rpg_universes', true);

        // 3. DEFINICJE GRY (Uniwersalny magazyn zasad - Umiejętności, Zdolności itp.)
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            
            // Kategoria: 'umiejetnosc', 'zdolnosc', 'czar' itp.
            'category'    => ['type' => 'VARCHAR', 'constraint' => 50], 
            
            'name'        => ['type' => 'VARCHAR', 'constraint' => 150],
            'description' => ['type' => 'TEXT', 'null' => true],
            
            // JSON na mechanikę (koszt XP, cecha testowana, modyfikatory)
            'metadata'    => ['type' => 'JSON', 'null' => true], 
            
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addPrimaryKey('id');
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addKey(['system_id', 'category']); // Indeks do szybkiego filtrowania
        $this->forge->createTable('game_definitions', true);

        // 4. AKTUALIZACJA POSTACI
        // Dodajemy informację o Systemie i Uniwersum do karty postaci
        if (!$this->db->fieldExists('system_id', 'characters')) {
            $this->forge->addColumn('characters', [
                'system_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'campaign_id'
                ],
                'universe_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true, 'after' => 'system_id'
                ]
            ]);
            
            // Bezpieczne dodanie kluczy obcych za pomocą SQL
            // Używamy nazwy fk_chars_... aby łatwiej nimi zarządzać
            // Używamy TRY-CATCH lub sprawdzamy błędy przy ręcznym SQL, ale tutaj w UP zakładamy, że struktura jest czysta
            $this->db->query('ALTER TABLE `characters` ADD CONSTRAINT `fk_chars_system` FOREIGN KEY (`system_id`) REFERENCES `rpg_systems` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
            $this->db->query('ALTER TABLE `characters` ADD CONSTRAINT `fk_chars_universe` FOREIGN KEY (`universe_id`) REFERENCES `rpg_universes` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE');
        }
    }

    public function down()
    {
        // 1. Usuwanie kluczy obcych z tabeli characters
        // Sprawdzamy czy tabela istnieje, aby uniknąć błędów
        if ($this->db->tableExists('characters')) {
            
            // FIX: Otaczamy usuwanie kluczy blokiem try-catch.
            // Jeśli klucz nie istnieje (bo np. migracja UP padła w połowie), to ignorujemy błąd i idziemy dalej.
            try {
                $this->db->query('ALTER TABLE `characters` DROP FOREIGN KEY `fk_chars_universe`');
            } catch (\Throwable $e) {
                // Klucz nie istniał - kontynuujemy rollback
            }

            try {
                $this->db->query('ALTER TABLE `characters` DROP FOREIGN KEY `fk_chars_system`');
            } catch (\Throwable $e) {
                // Klucz nie istniał - kontynuujemy rollback
            }
            
            // Usuwamy kolumny tylko jeśli istnieją
            $fieldsToDrop = [];
            if ($this->db->fieldExists('system_id', 'characters')) {
                $fieldsToDrop[] = 'system_id';
            }
            if ($this->db->fieldExists('universe_id', 'characters')) {
                $fieldsToDrop[] = 'universe_id';
            }
            
            if (!empty($fieldsToDrop)) {
                $this->forge->dropColumn('characters', $fieldsToDrop);
            }
        }

        // 2. Usuwanie tabel w odwrotnej kolejności (zależności)
        $this->forge->dropTable('game_definitions', true);
        $this->forge->dropTable('rpg_universes', true);
        $this->forge->dropTable('rpg_systems', true);
    }
}