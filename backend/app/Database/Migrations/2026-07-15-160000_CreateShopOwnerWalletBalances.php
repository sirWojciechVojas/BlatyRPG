<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateShopOwnerWalletBalances extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('shop_owner_wallet_balances')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'owner_code' => ['type' => 'VARCHAR', 'constraint' => 32],
                'currency_code' => ['type' => 'VARCHAR', 'constraint' => 64],
                'balance' => ['type' => 'INT', 'constraint' => 11, 'default' => 0],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['campaign_id', 'owner_code', 'currency_code'], 'uq_shop_owner_wallet_currency');
            $this->forge->addKey(['campaign_id', 'owner_code'], false, false, 'idx_shop_owner_wallet_owner');
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('shop_owner_wallet_balances', true);
        }

        if (!$this->db->tableExists('shop_owner_wallets')) {
            return;
        }

        $wallets = $this->db->table('shop_owner_wallets legacy')
            ->select('legacy.campaign_id, legacy.owner_code, legacy.brass_balance, campaigns.system_type')
            ->join('campaigns', 'campaigns.id = legacy.campaign_id', 'left')
            ->get()
            ->getResultArray();
        $now = date('Y-m-d H:i:s');

        foreach ($wallets as $wallet) {
            $currencyCode = $this->defaultCurrencyCode((string) ($wallet['system_type'] ?? 'generic'));
            $exists = $this->db->table('shop_owner_wallet_balances')
                ->where('campaign_id', (int) $wallet['campaign_id'])
                ->where('owner_code', strtoupper((string) $wallet['owner_code']))
                ->where('currency_code', $currencyCode)
                ->countAllResults();
            if ($exists) {
                continue;
            }
            $this->db->table('shop_owner_wallet_balances')->insert([
                'campaign_id' => (int) $wallet['campaign_id'],
                'owner_code' => strtoupper((string) $wallet['owner_code']),
                'currency_code' => $currencyCode,
                'balance' => max(0, (int) ($wallet['brass_balance'] ?? 0)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropTable('shop_owner_wallet_balances', true);
    }

    private function defaultCurrencyCode(string $systemCode): string
    {
        $normalized = strtolower(trim($systemCode));
        if (in_array($normalized, ['wfrp2ed', 'wfrp', 'warhammer'], true)) {
            return 'wfrp_empire';
        }
        if (in_array($normalized, ['coc7e', 'coc', 'call_of_cthulhu', 'cthulhu'], true)) {
            return 'coc_usd_1920';
        }
        return 'generic';
    }
}
