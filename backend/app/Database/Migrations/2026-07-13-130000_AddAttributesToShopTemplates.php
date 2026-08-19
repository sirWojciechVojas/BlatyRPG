<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAttributesToShopTemplates extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('attributes_json', 'shop_templates')) {
            $this->forge->addColumn('shop_templates', [
                'attributes_json' => [
                    'type' => 'JSON',
                    'null' => true,
                    'after' => 'weapon_json',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('attributes_json', 'shop_templates')) {
            $this->forge->dropColumn('shop_templates', 'attributes_json');
        }
    }
}
