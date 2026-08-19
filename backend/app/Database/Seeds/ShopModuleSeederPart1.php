<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

trait ShopModuleSeederPart1
{
    public function run()
    {
        $db = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        $campaignId = $this->resolveCampaignId($db, $now);
        if (!$campaignId) {
            echo "ShopModuleSeeder: cannot resolve campaign_id.\n";
            return;
        }

        $this->seedWorldProfiles($db, $now);
        $this->seedCatalogNodes($db, $now);

        $shopIds = $this->seedShopsAndProfiles($db, $campaignId, $now);
        $templateIds = $this->seedTemplates($db, $campaignId, $now);
        $containerIds = $this->seedContainers($db, $campaignId, $shopIds, $now);

        $this->seedContainerTemplateItems($db, $campaignId, $containerIds, $templateIds, $now);
        $this->seedOwnerClaims($db, $campaignId, $now);
        $this->seedWallets($db, $campaignId, $now);

        echo "ShopModuleSeeder completed for campaign_id={$campaignId}.\n";
    }

    private function resolveCampaignId($db, string $now): ?int
    {
        $campaign = $db->table('campaigns')->orderBy('id', 'ASC')->get()->getRowArray();
        if ($campaign) {
            return (int) $campaign['id'];
        }

        $user = $db->table('users')->orderBy('id', 'ASC')->get()->getRowArray();
        if (!$user) {
            $db->table('users')->insert([
                'username' => 'shop_gm',
                'email' => 'shop_gm@example.com',
                'password_hash' => password_hash('shop_gm_password', PASSWORD_DEFAULT),
                'role' => 'gm',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $userId = (int) $db->insertID();
        } else {
            $userId = (int) $user['id'];
        }

        $db->table('campaigns')->insert([
            'game_master_id' => $userId,
            'name' => 'Shop Demo Campaign',
            'description' => 'Seeded campaign for shop module development.',
            'system_type' => 'wfrp2ed',
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return (int) $db->insertID();
    }

    private function seedWorldProfiles($db, string $now): void
    {
        $profiles = [
            [
                'id' => 'standard',
                'label_pl' => 'Standard',
                'label_en' => 'Standard',
                'description' => 'Domyslny profil swiata.',
                'impact_summary_pl' => 'Balans legalnych i codziennych towarow.',
                'modifiers_json' => json_encode([
                    'legalityBias' => ['legal' => 6, 'licensed' => 4, 'grey' => -2, 'illegal' => -12],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => 'kampania_tysiac_tronow_v3',
                'label_pl' => 'Kampania Tysiac Tronow v3',
                'label_en' => 'Thousand Thrones Campaign v3',
                'description' => 'Wiecej towarow granicznych i szarej strefy.',
                'impact_summary_pl' => 'Wiecej ryzykownych transakcji i nielegalnych towarow.',
                'modifiers_json' => json_encode([
                    'legalityBias' => ['legal' => 0, 'licensed' => 2, 'grey' => 12, 'illegal' => 20],
                ], JSON_UNESCAPED_UNICODE),
            ],
            [
                'id' => 'roznice_swiatow_v1',
                'label_pl' => 'Roznice Swiatow v1',
                'label_en' => 'World Differences v1',
                'description' => 'Mieszany profil handlu ogolnego i sezonowego.',
                'impact_summary_pl' => 'Promuje handel ogolny i sezonowy, ogranicza luksus.',
                'modifiers_json' => json_encode([
                    'priceTierBoosts' => ['cheap' => 10, 'mid' => 8, 'high' => -3, 'luxury' => -12],
                ], JSON_UNESCAPED_UNICODE),
            ],
        ];

        foreach ($profiles as $profile) {
            $exists = $db->table('shop_world_profiles')->where('id', $profile['id'])->countAllResults();
            if ($exists) {
                continue;
            }
            $db->table('shop_world_profiles')->insert(array_merge($profile, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function seedCatalogNodes($db, string $now): void
    {
        $nodes = [
            ['node_key' => 'general_trade', 'parent_key' => null, 'level' => 'domain', 'name_pl' => 'Handel ogolny', 'name_en' => 'General Trade', 'description_pl' => 'Codzienny handel.'],
            ['node_key' => 'general_shops', 'parent_key' => 'general_trade', 'level' => 'group', 'name_pl' => 'Sklepy ogolne', 'name_en' => 'General Shops', 'description_pl' => 'Towary codzienne.'],
            ['node_key' => 'general_stall', 'parent_key' => 'general_shops', 'level' => 'type', 'name_pl' => 'Kram ogolny', 'name_en' => 'General Stall', 'description_pl' => 'Sprzedaz wszystkiego po trochu.'],
            ['node_key' => 'metal_weapons', 'parent_key' => null, 'level' => 'domain', 'name_pl' => 'Metal i bron', 'name_en' => 'Metal and Weapons', 'description_pl' => 'Uzbrojenie i pancerze.'],
            ['node_key' => 'metal_arms', 'parent_key' => 'metal_weapons', 'level' => 'group', 'name_pl' => 'Bron i ochrona', 'name_en' => 'Arms and Defense', 'description_pl' => 'Bron i ochrona.'],
            ['node_key' => 'armorer', 'parent_key' => 'metal_arms', 'level' => 'type', 'name_pl' => 'Platnerz', 'name_en' => 'Armorer', 'description_pl' => 'Pancerze i naprawy zbroi.'],
            ['node_key' => 'alchemy_medicine', 'parent_key' => null, 'level' => 'domain', 'name_pl' => 'Alchemia i medycyna', 'name_en' => 'Alchemy and Medicine', 'description_pl' => 'Leczenie i alchemia.'],
            ['node_key' => 'apothecary', 'parent_key' => 'alchemy_medicine', 'level' => 'type', 'name_pl' => 'Aptekarz', 'name_en' => 'Apothecary', 'description_pl' => 'Masci, proszki i nalewki.'],
        ];

        foreach ($nodes as $node) {
            $exists = $db->table('shop_catalog_nodes')->where('node_key', $node['node_key'])->countAllResults();
            if ($exists) {
                continue;
            }
            $db->table('shop_catalog_nodes')->insert(array_merge($node, [
                'payload_json' => json_encode(['suggestionRules' => ['requiredItemClasses' => []]], JSON_UNESCAPED_UNICODE),
                'created_at' => $now,
                'updated_at' => $now,
            ]));
        }
    }

    private function seedShopsAndProfiles($db, int $campaignId, string $now): array
    {
        $shops = [
            ['name' => 'Pod Kuflem Piwa', 'owner_code' => 'BG1', 'owner_name' => 'Otto Kramer', 'is_active' => 1, 'type_id' => 'general_stall'],
            ['name' => 'Kuznia Zbrojmistrza', 'owner_code' => 'BG1', 'owner_name' => 'Zbrojmistrz', 'is_active' => 1, 'type_id' => 'platnerz'],
            ['name' => 'Alchemik Bazyl', 'owner_code' => 'BG1', 'owner_name' => 'Bazyl', 'is_active' => 1, 'type_id' => 'alchemik'],
        ];

        $ids = [];
        foreach ($shops as $idx => $shop) {
            $row = $db->table('shops')
                ->where('campaign_id', $campaignId)
                ->where('name', $shop['name'])
                ->get()
                ->getRowArray();

            if (!$row) {
                $db->table('shops')->insert(array_merge($shop, [
                    'campaign_id' => $campaignId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $shopId = (int) $db->insertID();
            } else {
                $shopId = (int) $row['id'];
            }

            $ids[$idx + 1] = $shopId;

            $profileExists = $db->table('shop_profiles')
                ->where('campaign_id', $campaignId)
                ->where('shop_id', $shopId)
                ->countAllResults();

            if (!$profileExists) {
                $db->table('shop_profiles')->insert([
                    'campaign_id' => $campaignId,
                    'shop_id' => $shopId,
                    'type_id' => $shop['type_id'] ?? 'general_stall',
                    'signboard_name' => $shop['name'],
                    'owner_code' => $shop['owner_code'],
                    'owner_name' => $shop['owner_name'],
                    'signboard_alt_names_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'category_tags_json' => json_encode([], JSON_UNESCAPED_UNICODE),
                    'world_profile_id' => 'standard',
                    'location_type' => 'miasto',
                    'legal_status' => 'legal',
                    'wealth_tier' => 'standard',
                    'reputation' => 'neutralna',
                    'seasonality' => 'caloroczny',
                    'counterfeit_risk' => 10,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        return $ids;
    }
}
