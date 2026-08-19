<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

trait ShopModuleSeederPart3
{
    private function seedOwnerClaims($db, int $campaignId, string $now): void
    {
        $user = $db->table('users')->orderBy('id', 'ASC')->get()->getRowArray();
        if (!$user) {
            return;
        }

        $character = $db->table('characters')
            ->where('campaign_id', $campaignId)
            ->where('user_id', (int) $user['id'])
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        $existing = $db->table('shop_owner_claims')
            ->where('campaign_id', $campaignId)
            ->where('user_id', (int) $user['id'])
            ->where('owner_code', 'BG1')
            ->get()
            ->getRowArray();

        if ($existing) {
            if ($character && empty($existing['character_id'])) {
                $db->table('shop_owner_claims')
                    ->where('id', (int) $existing['id'])
                    ->update([
                        'character_id' => (int) $character['id'],
                        'updated_at' => $now,
                    ]);
            }
            return;
        }

        $db->table('shop_owner_claims')->insert([
            'campaign_id' => $campaignId,
            'user_id' => (int) $user['id'],
            'owner_code' => 'BG1',
            'character_id' => $character ? (int) $character['id'] : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
