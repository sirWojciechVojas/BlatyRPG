<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddTokenRotationFacingLink extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')
            || $this->db->fieldExists('rotation_follows_facing', 'scene_tokens')) {
            return;
        }
        $this->forge->addColumn('scene_tokens', [
            'rotation_follows_facing' => [
                'type' => 'TINYINT',
                'constraint' => 1,
                'default' => 0,
                'after' => 'facing_handle_enabled',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tokens')
            && $this->db->fieldExists('rotation_follows_facing', 'scene_tokens')) {
            $this->forge->dropColumn('scene_tokens', 'rotation_follows_facing');
        }
    }
}
