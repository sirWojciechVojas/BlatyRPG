<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenInfoVisibility extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')
            || $this->db->fieldExists('show_info_unselected', 'scene_tokens')) {
            return;
        }
        $this->forge->addColumn('scene_tokens', [
            'show_info_unselected' => [
                'type' => 'TINYINT', 'constraint' => 1, 'default' => 0,
                'after' => 'movement_reset_mode',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tokens')
            && $this->db->fieldExists('show_info_unselected', 'scene_tokens')) {
            $this->forge->dropColumn('scene_tokens', 'show_info_unselected');
        }
    }
}
