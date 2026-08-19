<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class EnsurePricingConfigOnShopProfiles extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('pricing_config_json', 'shop_profiles')) {
            $this->forge->addColumn('shop_profiles', [
                'pricing_config_json' => ['type' => 'JSON', 'null' => true],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('pricing_config_json', 'shop_profiles')) {
            $this->forge->dropColumn('shop_profiles', 'pricing_config_json');
        }
    }
}
