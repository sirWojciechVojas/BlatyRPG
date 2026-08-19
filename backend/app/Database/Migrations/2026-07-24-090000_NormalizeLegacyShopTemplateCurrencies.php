<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class NormalizeLegacyShopTemplateCurrencies extends Migration
{
    public function up()
    {
        $this->normalize(
            ['wfrp2ed', 'wfrp', 'warhammer'],
            'wfrp_empire'
        );
        $this->normalize(
            ['coc7e', 'coc', 'call_of_cthulhu', 'cthulhu'],
            'coc_usd_1920'
        );
    }

    public function down()
    {
        // Normalized values are valid explicit choices and must not be
        // converted back to the ambiguous legacy `generic` code.
    }

    private function normalize(array $systemCodes, string $currencyCode): void
    {
        $quotedSystems = implode(',', array_map(
            static fn (string $code): string => "'".addslashes($code)."'",
            $systemCodes
        ));
        $quotedCurrency = $this->db->escape($currencyCode);

        $this->db->query(
            "UPDATE shop_templates st
             INNER JOIN campaigns c ON c.id = st.campaign_id
             SET st.currency_code = {$quotedCurrency}
             WHERE LOWER(c.system_type) IN ({$quotedSystems})
               AND (
                   st.currency_code IS NULL
                   OR TRIM(st.currency_code) = ''
                   OR LOWER(st.currency_code) = 'generic'
               )"
        );
    }
}
