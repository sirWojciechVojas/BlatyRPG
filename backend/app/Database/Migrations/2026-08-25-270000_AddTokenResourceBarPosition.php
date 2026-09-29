<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenResourceBarPosition extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')
            || $this->db->fieldExists('resource_bar_position', 'scene_tokens')) {
            return;
        }
        $this->forge->addColumn('scene_tokens', [
            'resource_bar_position' => [
                'type' => 'VARCHAR', 'constraint' => 24, 'default' => 'below',
                'after' => 'show_info_unselected',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tokens')
            && $this->db->fieldExists('resource_bar_position', 'scene_tokens')) {
            $this->forge->dropColumn('scene_tokens', 'resource_bar_position');
        }
    }
}
