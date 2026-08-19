<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddMechanicsToShopItemDomains extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('mechanics_json', 'shop_item_dictionary_entries')) {
            $this->forge->addColumn('shop_item_dictionary_entries', [
                'mechanics_json' => [
                    'type' => 'JSON',
                    'null' => true,
                    'after' => 'applies_to_json',
                ],
            ]);
        }
        if (!$this->db->fieldExists('mechanics_json', 'shop_templates')) {
            $this->forge->addColumn('shop_templates', [
                'mechanics_json' => [
                    'type' => 'JSON',
                    'null' => true,
                    'after' => 'attributes_json',
                ],
            ]);
        }
        if (!$this->db->fieldExists('mechanics_mode', 'shop_templates')) {
            $this->forge->addColumn('shop_templates', [
                'mechanics_mode' => [
                    'type' => 'VARCHAR',
                    'constraint' => 16,
                    'default' => 'EXTEND',
                    'after' => 'mechanics_json',
                ],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('mechanics_mode', 'shop_templates')) {
            $this->forge->dropColumn('shop_templates', 'mechanics_mode');
        }
        if ($this->db->fieldExists('mechanics_json', 'shop_templates')) {
            $this->forge->dropColumn('shop_templates', 'mechanics_json');
        }
        if ($this->db->fieldExists('mechanics_json', 'shop_item_dictionary_entries')) {
            $this->forge->dropColumn('shop_item_dictionary_entries', 'mechanics_json');
        }
    }
}
