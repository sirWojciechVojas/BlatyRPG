<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddIsActiveToPivot extends Migration
{
    public function up()
    {
        // Dodajemy kolumnę, która określa, czy dana gra (połączenie systemu i świata)
        // jest widoczna dla użytkowników (np. w kreatorze postaci).
        $this->forge->addColumn('rpg_system_universes', [
            'is_active' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0, // Domyślnie wyłączone (faza deweloperska)
                'after'      => 'universe_id'
            ]
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('rpg_system_universes', 'is_active');
    }
}