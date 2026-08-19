<?php

namespace App\Database\Migrations;

use App\Services\Shop\ShopPricingService;
use App\Services\Shop\ShopProfileService;
use CodeIgniter\Database\Migration;

class EnsureWfrpShopPaymentProfiles extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('shops') || !$this->db->tableExists('shop_profiles')) {
            return;
        }
        $shops = $this->db->table('shops AS shop')
            ->select('shop.id, shop.campaign_id, shop.name, shop.owner_code, shop.owner_name, profile.id AS profile_id')
            ->join('campaigns AS campaign', 'campaign.id = shop.campaign_id')
            ->join(
                'shop_profiles AS profile',
                'profile.shop_id = shop.id AND profile.campaign_id = shop.campaign_id',
                'left'
            )
            ->where("LOWER(campaign.system_type) IN ('wfrp2ed', 'wfrp', 'warhammer')", null, false)
            ->where('shop.deleted_at', null)
            ->get()->getResultArray();

        $profiles = new ShopProfileService();
        foreach ($shops as $shop) {
            if (!empty($shop['profile_id'])) {
                continue;
            }
            $config = ShopPricingService::defaultPricingConfig();
            $config['currencyPolicy']['settlementCurrencyCode'] = 'wfrp_empire';
            $config['currencyPolicy']['exchangeRates'] = [
                'wfrp_empire' => 1.0,
                'wfrp_bretonnia' => 1.0,
            ];
            $config['currencyPolicy']['paymentExchangeFeePercent'] = 5.0;
            $profiles->upsertProfile((int) $shop['campaign_id'], (int) $shop['id'], [
                'signboardName' => (string) $shop['name'],
                'ownerCode' => (string) ($shop['owner_code'] ?? 'NPC'),
                'ownerName' => (string) ($shop['owner_name'] ?? ''),
                'pricingConfig' => $config,
            ], null, 'payment_policy_migration');
        }
    }

    public function down()
    {
        // Profiles may have been edited after creation and are intentionally retained.
    }
}
