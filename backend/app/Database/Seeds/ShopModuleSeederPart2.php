<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

trait ShopModuleSeederPart2
{
    private function seedTemplates($db, int $campaignId, string $now): array
    {
        $templates = [
            ['name' => 'Miecz imperialny', 'description' => 'Klasyczny oroz Imperium.', 'details' => 'Standardowe uzbrojenie.', 'item_class' => 'WEAPON', 'item_id' => '13', 'item_genre' => 'MELEE', 'img_class' => 'v0422', 'prize' => 1920, 'charge' => 95, 'weapon_json' => ['TYPE' => 'sieczna', 'CATEGORY' => 'zwykla', 'DAMAGE' => 'S+2', 'QUALITIES' => 'wywazona', 'LOAD' => '95']],
            ['name' => 'Topor krasnoludzki', 'description' => 'Topor z hartowanej stali.', 'details' => 'Rzemioslo krasnoludow.', 'item_class' => 'WEAPON', 'item_id' => '17', 'item_genre' => 'MELEE', 'img_class' => 'v0201', 'prize' => 2160, 'charge' => 100, 'weapon_json' => ['TYPE' => 'sieczna', 'CATEGORY' => 'zwykla', 'DAMAGE' => 'S+3', 'QUALITIES' => 'druzgocacy', 'LOAD' => '100']],
            ['name' => 'Kusza lekka', 'description' => 'Lekka kusza polowa.', 'details' => 'Solidny naciag.', 'item_class' => 'WEAPON', 'item_id' => '21', 'item_genre' => 'RANGED', 'img_class' => 'v0170', 'prize' => 1440, 'charge' => 45, 'weapon_json' => ['TYPE' => 'miotajaca', 'CATEGORY' => 'zwykla', 'DAMAGE' => 'S+4', 'RANGE' => '30/60', 'RELOAD' => 'pelna akcja', 'LOAD' => '45']],
            ['name' => 'Zbroja kolcza', 'description' => 'Ciezka kolczuga.', 'details' => 'Wzmacniana przeszyciem.', 'item_class' => 'ARMOR', 'item_id' => '44', 'item_genre' => 'BODY', 'img_class' => 'v0619', 'prize' => 3600, 'charge' => 250],
            ['name' => 'Eliksir leczenia', 'description' => 'Leczaca mikstura.', 'details' => 'Limitowana dostawa.', 'item_class' => 'ALCHEMY', 'item_id' => '112', 'item_genre' => 'POTION', 'img_class' => 'v1089', 'prize' => 960, 'charge' => 25],
            ['name' => 'Eliksir wzmocnienia', 'description' => 'Gorzki wywar wzmacniajacy.', 'details' => 'Dzialanie zalezy od dawki i kondycji postaci.', 'item_class' => 'ALCHEMY', 'item_id' => '113', 'item_genre' => 'POTION', 'img_class' => 'v1090', 'prize' => 1320, 'charge' => 25],
        ];

        $ids = [];
        foreach ($templates as $template) {
            $row = $db->table('shop_templates')
                ->where('campaign_id', $campaignId)
                ->where('name', $template['name'])
                ->get()
                ->getRowArray();

            if (!$row) {
                $db->table('shop_templates')->insert(array_merge($template, [
                    'campaign_id' => $campaignId,
                    'draft' => 0,
                    'weapon_json' => isset($template['weapon_json']) ? json_encode($template['weapon_json'], JSON_UNESCAPED_UNICODE) : null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
                $id = (int) $db->insertID();
            } elseif (!empty($template['weapon_json']) && empty($row['weapon_json'])) {
                $db->table('shop_templates')
                    ->where('id', (int) $row['id'])
                    ->update([
                        'weapon_json' => json_encode($template['weapon_json'], JSON_UNESCAPED_UNICODE),
                        'updated_at' => $now,
                    ]);
                $id = (int) $row['id'];
            } else {
                $id = (int) $row['id'];
            }

            $ids[$template['name']] = $id;
        }

        return $ids;
    }

    private function seedContainers($db, int $campaignId, array $shopIds, string $now): array
    {
        $definitions = [
            ['container_type' => 'SYSTEM', 'system_key' => 'DEFAULT', 'owner_code' => null, 'shop_id' => null, 'name' => 'DEFAULT', 'capacity' => null],
            ['container_type' => 'SYSTEM', 'system_key' => 'TRASH', 'owner_code' => null, 'shop_id' => null, 'name' => 'TRASH', 'capacity' => null],
            ['container_type' => 'CHARACTER', 'system_key' => null, 'owner_code' => 'BG1', 'shop_id' => null, 'name' => 'BG1 - Ekwipunek', 'capacity' => null],
            ['container_type' => 'TRASH', 'system_key' => null, 'owner_code' => 'BG1', 'shop_id' => null, 'name' => 'BG1 - Kosz', 'capacity' => 16],
        ];

        foreach ($shopIds as $shopId) {
            $definitions[] = [
                'container_type' => 'SHOP',
                'system_key' => null,
                'owner_code' => null,
                'shop_id' => $shopId,
                'name' => 'Sklep '.$shopId.' - Asortyment',
                'capacity' => null,
            ];
        }

        $ids = [];

        foreach ($definitions as $def) {
            $query = $db->table('shop_containers')
                ->where('campaign_id', $campaignId)
                ->where('container_type', $def['container_type']);

            if ($def['system_key'] !== null) {
                $query->where('system_key', $def['system_key']);
            }
            if ($def['owner_code'] !== null) {
                $query->where('owner_code', $def['owner_code']);
            }
            if ($def['shop_id'] !== null) {
                $query->where('shop_id', $def['shop_id']);
            }

            $row = $query->get()->getRowArray();

            if (!$row) {
                $db->table('shop_containers')->insert([
                    'campaign_id' => $campaignId,
                    'shop_id' => $def['shop_id'],
                    'container_type' => $def['container_type'],
                    'system_key' => $def['system_key'],
                    'owner_code' => $def['owner_code'],
                    'name' => $def['name'],
                    'capacity' => $def['capacity'],
                    'is_active' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $id = (int) $db->insertID();
            } else {
                $id = (int) $row['id'];
            }

            $key = $def['container_type'];
            if ($def['system_key']) {
                $key .= ':'.$def['system_key'];
            }
            if ($def['owner_code']) {
                $key .= ':'.$def['owner_code'];
            }
            if ($def['shop_id']) {
                $key .= ':'.$def['shop_id'];
            }

            $ids[$key] = $id;
        }

        return $ids;
    }

    private function seedContainerTemplateItems($db, int $campaignId, array $containerIds, array $templateIds, string $now): void
    {
        $rows = [
            ['container_key' => 'SYSTEM:DEFAULT', 'template_name' => 'Eliksir leczenia', 'quantity' => 2, 'price_override' => null],
            ['container_key' => 'SHOP:1', 'template_name' => 'Miecz imperialny', 'quantity' => 2, 'price_override' => null],
            ['container_key' => 'SHOP:1', 'template_name' => 'Topor krasnoludzki', 'quantity' => 1, 'price_override' => null],
            ['container_key' => 'SHOP:1', 'template_name' => 'Topór krasnoludzki', 'quantity' => 1, 'price_override' => null],
            ['container_key' => 'SHOP:2', 'template_name' => 'Kusza lekka', 'quantity' => 3, 'price_override' => null],
            ['container_key' => 'SHOP:2', 'template_name' => 'Zbroja kolcza', 'quantity' => 1, 'price_override' => null],
            ['container_key' => 'SHOP:3', 'template_name' => 'Eliksir wzmocnienia', 'quantity' => 8, 'price_override' => null],
        ];

        foreach ($rows as $row) {
            $containerId = $containerIds[$row['container_key']] ?? null;
            $templateId = $templateIds[$row['template_name']] ?? null;

            if (!$containerId || !$templateId) {
                continue;
            }

            $exists = $db->table('shop_container_template_items')
                ->where('container_id', $containerId)
                ->where('template_id', $templateId)
                ->countAllResults();

            if ($exists) {
                continue;
            }

            $db->table('shop_container_template_items')->insert([
                'campaign_id' => $campaignId,
                'container_id' => $containerId,
                'template_id' => $templateId,
                'quantity' => $row['quantity'],
                'price_override' => $row['price_override'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    private function seedWallets($db, int $campaignId, string $now): void
    {
        $walletExists = $db->table('shop_owner_wallets')
            ->where('campaign_id', $campaignId)
            ->where('owner_code', 'BG1')
            ->countAllResults();

        if (!$walletExists) {
            $db->table('shop_owner_wallets')->insert([
                'campaign_id' => $campaignId,
                'owner_code' => 'BG1',
                'brass_balance' => 2875,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($db->tableExists('shop_owner_wallet_balances')) {
            $balanceExists = $db->table('shop_owner_wallet_balances')
                ->where('campaign_id', $campaignId)
                ->where('owner_code', 'BG1')
                ->where('currency_code', 'wfrp_empire')
                ->countAllResults();
            if (!$balanceExists) {
                $db->table('shop_owner_wallet_balances')->insert([
                    'campaign_id' => $campaignId,
                    'owner_code' => 'BG1',
                    'currency_code' => 'wfrp_empire',
                    'balance' => 2875,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if ($db->fieldExists('brass', 'characters')) {
            $claim = $db->table('shop_owner_claims')
                ->where('campaign_id', $campaignId)
                ->where('owner_code', 'BG1')
                ->get()
                ->getRowArray();
            if (!empty($claim['character_id'])) {
                $balance = $db->table('shop_owner_wallets')
                    ->select('brass_balance')
                    ->where('campaign_id', $campaignId)
                    ->where('owner_code', 'BG1')
                    ->get()
                    ->getRowArray();
                $db->table('characters')
                    ->where('id', (int) $claim['character_id'])
                    ->update(['brass' => max(0, (int) ($balance['brass_balance'] ?? 0))]);
            }
        }
    }
}
