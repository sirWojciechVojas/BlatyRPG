<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddFacingToSceneTokens extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('scene_tokens')
            || $this->db->fieldExists('facing', 'scene_tokens')) {
            return;
        }
        $this->forge->addColumn('scene_tokens', [
            'facing' => [
                'type' => 'DECIMAL',
                'constraint' => '7,3',
                'default' => 0,
                'after' => 'rotation',
            ],
        ]);
        $this->db->table('scene_tokens')->set('facing', 'rotation', false)->update();
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tokens')
            && $this->db->fieldExists('facing', 'scene_tokens')) {
            $this->forge->dropColumn('scene_tokens', 'facing');
        }
    }
}
