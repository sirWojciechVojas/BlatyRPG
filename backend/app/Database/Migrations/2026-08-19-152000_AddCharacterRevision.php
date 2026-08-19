<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCharacterRevision extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('characters')
            || $this->db->fieldExists('revision', 'characters')) {
            return;
        }
        $this->forge->addColumn('characters', [
            'revision' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'default' => 1,
                'after' => 'updated_at',
            ],
        ]);
    }

    public function down()
    {
        if ($this->db->tableExists('characters')
            && $this->db->fieldExists('revision', 'characters')) {
            $this->forge->dropColumn('characters', 'revision');
        }
    }
}
