<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds a stable, GM-editable order to each character's profession history. */
final class AddCharacterProfessionOrder extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('character_professions')
            || $this->db->fieldExists('sort_order', 'character_professions')) {
            return;
        }

        $this->forge->addColumn('character_professions', [
            'sort_order' => [
                'type' => 'INT',
                'constraint' => 11,
                'default' => 0,
                'after' => 'finished_at',
            ],
        ]);
        $this->db->resetDataCache();

        $rows = $this->db->table('character_professions')
            ->select('id,character_id')
            ->orderBy('character_id', 'ASC')
            ->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $positions = [];
        foreach ($rows as $row) {
            $characterId = (int) $row['character_id'];
            $positions[$characterId] = ($positions[$characterId] ?? 0) + 1;
            $this->db->table('character_professions')
                ->where('id', (int) $row['id'])
                ->update(['sort_order' => $positions[$characterId]]);
        }
    }

    public function down()
    {
        if ($this->db->tableExists('character_professions')
            && $this->db->fieldExists('sort_order', 'character_professions')) {
            $this->forge->dropColumn('character_professions', 'sort_order');
            $this->db->resetDataCache();
        }
    }
}
