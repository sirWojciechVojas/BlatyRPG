<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateHeroesTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'constraint' => 5, 'unsigned' => true, 'auto_increment' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'class'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'level'       => ['type' => 'INT', 'default' => 1],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('heroes');
    }

    public function down()
    {
        $this->forge->dropTable('heroes');
    }
}
