<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class LinkShopClaimsToCharacters extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('character_id', 'shop_owner_claims')) {
            $this->forge->addColumn('shop_owner_claims', [
                'character_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                    'after' => 'user_id',
                ],
            ]);
        }

        $claims = $this->db->table('shop_owner_claims')->where('character_id', null)->get()->getResultArray();
        foreach ($claims as $claim) {
            $characters = $this->db->table('characters')
                ->select('id')
                ->where('campaign_id', (int) $claim['campaign_id'])
                ->where('user_id', (int) $claim['user_id'])
                ->limit(2)
                ->get()
                ->getResultArray();

            if (count($characters) === 1) {
                $this->db->table('shop_owner_claims')
                    ->where('id', (int) $claim['id'])
                    ->update(['character_id' => (int) $characters[0]['id']]);
            }
        }

        $indexes = $this->db->getIndexData('shop_owner_claims');
        if (!array_key_exists('idx_shop_owner_claim_character', $indexes)) {
            $this->db->query(
                'CREATE INDEX `idx_shop_owner_claim_character` ON `shop_owner_claims` (`campaign_id`, `character_id`)'
            );
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('character_id', 'shop_owner_claims')) {
            $this->forge->dropColumn('shop_owner_claims', 'character_id');
        }
    }
}
