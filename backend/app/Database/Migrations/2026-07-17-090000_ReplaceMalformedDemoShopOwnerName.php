<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ReplaceMalformedDemoShopOwnerName extends Migration
{
    private const SHOP_NAME = 'Pod Kuflem Piwa';
    private const MALFORMED_NAME = 'Karczmiany';
    private const CORRECTED_NAME = 'Otto Kramer';

    public function up()
    {
        $this->replaceOwnerName(self::MALFORMED_NAME, self::CORRECTED_NAME);
    }

    public function down()
    {
        $this->replaceOwnerName(self::CORRECTED_NAME, self::MALFORMED_NAME);
    }

    private function replaceOwnerName(string $from, string $to): void
    {
        if (!$this->db->tableExists('shops')) {
            return;
        }

        if ($this->db->tableExists('shop_profiles')) {
            $profiles = $this->db->table('shop_profiles profile')
                ->select('profile.id')
                ->join(
                    'shops shop',
                    'shop.id = profile.shop_id AND shop.campaign_id = profile.campaign_id',
                    'inner'
                )
                ->where('shop.name', self::SHOP_NAME)
                ->where('profile.owner_name', $from)
                ->get()
                ->getResultArray();

            foreach ($profiles as $profile) {
                $this->db->table('shop_profiles')
                    ->where('id', (int) $profile['id'])
                    ->update([
                        'owner_name' => $to,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            }
        }

        $this->db->table('shops')
            ->where('name', self::SHOP_NAME)
            ->where('owner_name', $from)
            ->update([
                'owner_name' => $to,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }
}
