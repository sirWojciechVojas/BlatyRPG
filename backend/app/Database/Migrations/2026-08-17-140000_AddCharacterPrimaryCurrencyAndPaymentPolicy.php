<?php

namespace App\Database\Migrations;

use App\Services\Shop\ShopPricingService;
use CodeIgniter\Database\Migration;

class AddCharacterPrimaryCurrencyAndPaymentPolicy extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('primary_currency_code', 'characters')) {
            $this->forge->addColumn('characters', [
                'primary_currency_code' => [
                    'type' => 'VARCHAR',
                    'constraint' => 64,
                    'null' => true,
                    'after' => 'brass',
                ],
            ]);
        }

        $this->db->query(
            "UPDATE characters AS character_row "
            . "INNER JOIN rpg_systems AS system_row ON system_row.id = character_row.system_id "
            . "SET character_row.primary_currency_code = CASE "
            . "WHEN LOWER(system_row.code) IN ('wfrp2ed', 'wfrp', 'warhammer') "
            . "AND JSON_UNQUOTE(JSON_EXTRACT(character_row.data, '$.meta.campaign_name')) = '3' "
            . "THEN 'wfrp_bretonnia' "
            . "WHEN LOWER(system_row.code) IN ('wfrp2ed', 'wfrp', 'warhammer') THEN 'wfrp_empire' "
            . "WHEN LOWER(system_row.code) IN ('coc7e', 'coc', 'call_of_cthulhu', 'cthulhu') THEN 'coc_usd_1920' "
            . "ELSE 'generic' END "
            . "WHERE character_row.primary_currency_code IS NULL OR character_row.primary_currency_code = ''"
        );

        $this->migrateCharacterWallets();
        $this->upgradeWfrpShopProfiles();
    }

    private function migrateCharacterWallets(): void
    {
        if (!$this->db->tableExists('shop_owner_wallet_balances')) {
            return;
        }

        $characters = $this->db->table('characters')
            ->select('id, campaign_id, brass, primary_currency_code')
            ->get()->getResultArray();
        foreach ($characters as $character) {
            $targets = [];
            $directOwnerCode = 'CHAR_' . (int) $character['id'];
            $directWallets = $this->db->table('shop_owner_wallet_balances')
                ->select('campaign_id')
                ->where('owner_code', $directOwnerCode)
                ->get()->getResultArray();
            foreach ($directWallets as $directWallet) {
                $targets[] = [(int) $directWallet['campaign_id'], $directOwnerCode];
            }
            $characterCampaignId = (int) ($character['campaign_id'] ?? 0);
            if ($characterCampaignId > 0) {
                $targets[] = [$characterCampaignId, $directOwnerCode];
            }
            if ($this->db->tableExists('shop_owner_claims')) {
                $claims = $this->db->table('shop_owner_claims')
                    ->select('campaign_id, owner_code')
                    ->where('character_id', (int) $character['id'])
                    ->get()->getResultArray();
                foreach ($claims as $claim) {
                    $targets[] = [
                        (int) $claim['campaign_id'],
                        strtoupper((string) $claim['owner_code']),
                    ];
                }
            }
            $uniqueTargets = [];
            foreach ($targets as [$campaignId, $ownerCode]) {
                if ($campaignId > 0) {
                    $uniqueTargets[$campaignId . ':' . $ownerCode] = [$campaignId, $ownerCode];
                }
            }
            foreach ($uniqueTargets as [$campaignId, $ownerCode]) {
                $this->migrateOwnerWallet(
                    $campaignId,
                    $ownerCode,
                    strtolower((string) $character['primary_currency_code']),
                    max(0, (int) ($character['brass'] ?? 0))
                );
            }
        }
    }

    private function migrateOwnerWallet(int $campaignId, string $ownerCode, string $primaryCode, int $brass): void
    {
        $table = $this->db->table('shop_owner_wallet_balances');
        $primary = $table->where('campaign_id', $campaignId)
            ->where('owner_code', $ownerCode)->where('currency_code', $primaryCode)
            ->get()->getRowArray();
        if ($primary) {
            $table->where('id', (int) $primary['id'])->update(['balance' => $brass]);
        } else {
            $table->insert([
                'campaign_id' => $campaignId,
                'owner_code' => $ownerCode,
                'currency_code' => $primaryCode,
                'balance' => $brass,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        if ($primaryCode === 'wfrp_bretonnia') {
            $imperial = $table->where('campaign_id', $campaignId)
                ->where('owner_code', $ownerCode)->where('currency_code', 'wfrp_empire')
                ->get()->getRowArray();
            if ($imperial && (int) $imperial['balance'] === $brass) {
                $table->where('id', (int) $imperial['id'])->delete();
            }
        }
    }

    private function upgradeWfrpShopProfiles(): void
    {
        if (!$this->db->tableExists('shop_profiles')) {
            return;
        }
        $rows = $this->db->table('shop_profiles AS profile')
            ->select('profile.id, profile.pricing_config_json')
            ->join('campaigns AS campaign', 'campaign.id = profile.campaign_id')
            ->where("LOWER(campaign.system_type) IN ('wfrp2ed', 'wfrp', 'warhammer')", null, false)
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $raw = $row['pricing_config_json'] ?? [];
            if (is_string($raw)) {
                $raw = json_decode($raw, true) ?: [];
            }
            $config = ShopPricingService::normalizePricingConfig($raw);
            $config['version'] = 4;
            $config['currencyPolicy']['exchangeRates']['wfrp_empire'] = 1.0;
            $config['currencyPolicy']['exchangeRates']['wfrp_bretonnia'] = 1.0;
            $config['currencyPolicy']['paymentExchangeFeePercent'] = 5.0;
            $this->db->table('shop_profiles')->where('id', (int) $row['id'])->update([
                'pricing_config_json' => json_encode($config, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('primary_currency_code', 'characters')) {
            $this->forge->dropColumn('characters', 'primary_currency_code');
        }
    }
}
