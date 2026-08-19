<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAvatarAndBrassToCharacters extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('avatar', 'characters')) {
            $this->forge->addColumn('characters', [
                'avatar' => [
                    'type' => 'VARCHAR',
                    'constraint' => 255,
                    'null' => true,
                    'after' => 'avatar_url',
                ],
            ]);
        }

        if (!$this->db->fieldExists('brass', 'characters')) {
            $this->forge->addColumn('characters', [
                'brass' => [
                    'type' => 'INT',
                    'constraint' => 11,
                    'unsigned' => true,
                    'default' => 0,
                    'after' => 'avatar',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE characters SET avatar = avatar_url WHERE (avatar IS NULL OR avatar = '') AND avatar_url IS NOT NULL"
        );

        if ($this->db->tableExists('shop_owner_claims') && $this->db->tableExists('shop_owner_wallets')) {
            $this->db->query(
                'UPDATE characters AS c '
                . 'INNER JOIN shop_owner_claims AS claim ON claim.character_id = c.id '
                . 'INNER JOIN shop_owner_wallets AS wallet '
                . 'ON wallet.campaign_id = claim.campaign_id AND wallet.owner_code = claim.owner_code '
                . 'SET c.brass = GREATEST(0, wallet.brass_balance)'
            );
        }

        if ($this->db->tableExists('shop_owner_wallets')) {
            $this->db->query(
                "UPDATE characters AS c INNER JOIN shop_owner_wallets AS wallet "
                . "ON wallet.owner_code = CONCAT('CHAR_', c.id) "
                . 'SET c.brass = GREATEST(0, wallet.brass_balance)'
            );
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('brass', 'characters')) {
            $this->forge->dropColumn('characters', 'brass');
        }
        if ($this->db->fieldExists('avatar', 'characters')) {
            $this->forge->dropColumn('characters', 'avatar');
        }
    }
}
