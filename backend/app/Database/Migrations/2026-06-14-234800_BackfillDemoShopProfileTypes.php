<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class BackfillDemoShopProfileTypes extends Migration
{
    public function up()
    {
        $this->backfillType('Kuznia Zbrojmistrza', 'platnerz');
        $this->backfillType('Alchemik Bazyl', 'alchemik');
    }

    public function down()
    {
        $this->restoreType('Kuznia Zbrojmistrza', 'platnerz');
        $this->restoreType('Alchemik Bazyl', 'alchemik');
    }

    private function backfillType(string $shopName, string $typeId): void
    {
        $rows = $this->db->table('shop_profiles profile')
            ->select('profile.id')
            ->join(
                'shops shop',
                'shop.id = profile.shop_id AND shop.campaign_id = profile.campaign_id',
                'inner'
            )
            ->where('profile.type_id', 'general_stall')
            ->where('shop.name', $shopName)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('shop_profiles')
                ->where('id', (int) $row['id'])
                ->update([
                    'type_id' => $typeId,
                    'category_tags_json' => json_encode(['typ:'.$typeId], JSON_UNESCAPED_UNICODE),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }

    private function restoreType(string $shopName, string $typeId): void
    {
        $rows = $this->db->table('shop_profiles profile')
            ->select('profile.id')
            ->join(
                'shops shop',
                'shop.id = profile.shop_id AND shop.campaign_id = profile.campaign_id',
                'inner'
            )
            ->where('profile.type_id', $typeId)
            ->where('shop.name', $shopName)
            ->get()
            ->getResultArray();

        foreach ($rows as $row) {
            $this->db->table('shop_profiles')
                ->where('id', (int) $row['id'])
                ->update([
                    'type_id' => 'general_stall',
                    'category_tags_json' => json_encode(['typ:general_stall'], JSON_UNESCAPED_UNICODE),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
        }
    }
}
