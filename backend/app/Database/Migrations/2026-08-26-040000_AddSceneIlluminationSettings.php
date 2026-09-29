<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSceneIlluminationSettings extends Migration
{
    private const COLUMNS = [
        'global_illumination' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'fog_exploration' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
    ];

    public function up()
    {
        foreach (self::COLUMNS as $name => $definition) {
            if (!$this->db->fieldExists($name, 'scenes')) {
                $this->forge->addColumn('scenes', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(array_keys(self::COLUMNS)) as $name) {
            if ($this->db->fieldExists($name, 'scenes')) {
                $this->forge->dropColumn('scenes', $name);
            }
        }
    }
}
