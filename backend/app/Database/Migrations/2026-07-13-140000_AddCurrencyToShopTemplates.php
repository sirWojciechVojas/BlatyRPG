<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCurrencyToShopTemplates extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('currency_code', 'shop_templates')) {
            $this->forge->addColumn('shop_templates', [
                'currency_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'default' => 'generic',
                    'after' => 'prize',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE shop_templates st
             INNER JOIN campaigns c ON c.id = st.campaign_id
             SET st.currency_code = 'wfrp_empire'
             WHERE LOWER(c.system_type) IN ('wfrp2ed', 'wfrp', 'warhammer')
               AND (st.currency_code IS NULL OR st.currency_code = '' OR st.currency_code = 'generic')"
        );
    }

    public function down()
    {
        if ($this->db->fieldExists('currency_code', 'shop_templates')) {
            $this->forge->dropColumn('shop_templates', 'currency_code');
        }
    }
}
