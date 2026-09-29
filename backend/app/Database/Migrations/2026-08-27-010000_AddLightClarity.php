<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLightClarity extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('clarity', 'scene_lights')) {
            $this->forge->addColumn('scene_lights', [
                'clarity' => [
                    'type' => 'DECIMAL',
                    'constraint' => '4,3',
                    'default' => 0,
                    'after' => 'softness',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('clarity', 'scene_lights')) {
            $this->forge->dropColumn('scene_lights', 'clarity');
        }
    }
}
