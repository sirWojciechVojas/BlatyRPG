<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Stores an independent, reusable soundpad format for every campaign screen. */
class AddSoundEffectScreenLayout extends Migration
{
    private const COLUMNS = [
        'grid_columns' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 3],
        'grid_rows' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 4],
        'text_lines' => ['type' => 'TINYINT', 'constraint' => 3, 'unsigned' => true, 'default' => 1],
        'pad_style' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'square'],
    ];

    public function up()
    {
        if (!$this->db->tableExists('sound_effect_screens')) {
            return;
        }
        foreach (self::COLUMNS as $name => $definition) {
            if (!$this->db->fieldExists($name, 'sound_effect_screens')) {
                $this->forge->addColumn('sound_effect_screens', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        if (!$this->db->tableExists('sound_effect_screens')) {
            return;
        }
        foreach (array_reverse(array_keys(self::COLUMNS)) as $name) {
            if ($this->db->fieldExists($name, 'sound_effect_screens')) {
                $this->forge->dropColumn('sound_effect_screens', $name);
            }
        }
    }
}
