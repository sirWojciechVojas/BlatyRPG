<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCampaignMembers extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('campaign_members')) {
            $this->forge->addField([
                'id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'role' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'player'],
                'permissions_json' => ['type' => 'JSON', 'null' => true],
                'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['campaign_id', 'user_id']);
            $this->forge->addKey(['user_id', 'is_active']);
            $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
            $this->forge->createTable('campaign_members');
        }

        $this->backfillCampaignGameMasters();
        $this->backfillCharacterOwners();
        $this->backfillShopClaims();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_members', true);
    }

    private function backfillCampaignGameMasters(): void
    {
        if (!$this->db->tableExists('campaigns')
            || !$this->db->fieldExists('game_master_id', 'campaigns')) {
            return;
        }
        $rows = $this->db->table('campaigns c')
            ->select('c.id AS campaign_id, c.game_master_id AS user_id')
            ->join('users users', 'users.id = c.game_master_id', 'inner')
            ->where('c.game_master_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $this->addMember((int) $row['campaign_id'], (int) $row['user_id'], 'gm');
        }
    }

    private function backfillCharacterOwners(): void
    {
        if (!$this->hasMembershipColumns('characters')) {
            return;
        }
        $rows = $this->db->table('characters c')->distinct()
            ->select('c.campaign_id, c.user_id')
            ->join('campaigns campaigns', 'campaigns.id = c.campaign_id', 'inner')
            ->join('users users', 'users.id = c.user_id', 'inner')
            ->where('c.campaign_id IS NOT NULL', null, false)
            ->where('c.user_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $this->addMember((int) $row['campaign_id'], (int) $row['user_id'], 'player');
        }
    }

    private function backfillShopClaims(): void
    {
        if (!$this->hasMembershipColumns('shop_owner_claims')) {
            return;
        }
        $rows = $this->db->table('shop_owner_claims claims')->distinct()
            ->select('claims.campaign_id, claims.user_id')
            ->join('campaigns campaigns', 'campaigns.id = claims.campaign_id', 'inner')
            ->join('users users', 'users.id = claims.user_id', 'inner')
            ->where('claims.campaign_id IS NOT NULL', null, false)
            ->where('claims.user_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $this->addMember((int) $row['campaign_id'], (int) $row['user_id'], 'player');
        }
    }

    private function hasMembershipColumns(string $table): bool
    {
        return $this->db->tableExists($table)
            && $this->db->fieldExists('campaign_id', $table)
            && $this->db->fieldExists('user_id', $table);
    }

    private function addMember(int $campaignId, int $userId, string $role): void
    {
        if ($campaignId < 1 || $userId < 1) {
            return;
        }
        $table = $this->db->table('campaign_members');
        if ($table->where(['campaign_id' => $campaignId, 'user_id' => $userId])->countAllResults()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $table->insert([
            'campaign_id' => $campaignId,
            'user_id' => $userId,
            'role' => $role,
            'is_active' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
