<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSystemUniversePivot extends Migration
{
    public function up()
    {
        // 1. Tabela łącząca (Pivot Table)
        $this->forge->addField([
            'system_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'universe_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
        ]);
        
        // Klucz złożony (Para system+uniwersum musi być unikalna)
        $this->forge->addPrimaryKey(['system_id', 'universe_id']);
        
        // Klucze obce
        $this->forge->addForeignKey('system_id', 'rpg_systems', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('universe_id', 'rpg_universes', 'id', 'CASCADE', 'CASCADE');
        
        $this->forge->createTable('rpg_system_universes', true);

        // 2. Migracja danych (Seedowanie z istniejących relacji)
        // Pobieramy obecne powiązania z default_system_id i wpisujemy je do nowej tabeli,
        // żeby nie stracić danych i zachować spójność.
        $sql = "INSERT INTO rpg_system_universes (system_id, universe_id)
                SELECT default_system_id, id 
                FROM rpg_universes 
                WHERE default_system_id IS NOT NULL";
        
        $this->db->query($sql);
    }

    public function down()
    {
        $this->forge->dropTable('rpg_system_universes', true);
    }
}