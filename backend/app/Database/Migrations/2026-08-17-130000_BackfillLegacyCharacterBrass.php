<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillLegacyCharacterBrass extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('brass', 'characters')) {
            return;
        }

        $legacyTableExists = (bool) $this->db->query(
            "SELECT 1 FROM information_schema.tables WHERE table_schema = 'warhammer' AND table_name = 'w_bg_current' LIMIT 1"
        )->getRowArray();
        if (!$legacyTableExists) {
            return;
        }

        $this->db->query(
            'UPDATE characters AS character_row '
            . 'INNER JOIN warhammer.w_bg_current AS legacy ON legacy.USEDNAME_ID = character_row.id '
            . 'SET character_row.brass = GREATEST(0, legacy.BRASS) '
            . 'WHERE character_row.brass = 0'
        );

        if ($this->db->tableExists('shop_owner_wallet_balances')) {
            $this->db->query(
                'UPDATE shop_owner_wallet_balances AS wallet '
                . "INNER JOIN characters AS character_row ON wallet.owner_code = CONCAT('CHAR_', character_row.id) "
                . 'SET wallet.balance = character_row.brass '
                . "WHERE wallet.currency_code = 'wfrp_empire'"
            );
        }
        if ($this->db->tableExists('shop_owner_wallets')) {
            $this->db->query(
                'UPDATE shop_owner_wallets AS wallet '
                . "INNER JOIN characters AS character_row ON wallet.owner_code = CONCAT('CHAR_', character_row.id) "
                . 'SET wallet.brass_balance = character_row.brass'
            );
        }
    }

    public function down()
    {
        // Imported character state must not be destroyed on rollback.
    }
}
