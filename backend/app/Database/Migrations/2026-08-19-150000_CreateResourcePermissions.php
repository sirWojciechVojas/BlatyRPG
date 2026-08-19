<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateResourcePermissions extends Migration
{
    public function up()
    {
        $this->addCharacterVisibility();
        $this->createPermissions();
        $this->backfillCharacterOwners();
    }

    public function down()
    {
        $this->forge->dropTable('resource_permissions', true);
        if ($this->db->tableExists('characters')
            && $this->db->fieldExists('visibility_level', 'characters')) {
            $this->forge->dropColumn('characters', 'visibility_level');
        }
    }

    private function addCharacterVisibility(): void
    {
        if ($this->db->tableExists('characters')
            && !$this->db->fieldExists('visibility_level', 'characters')) {
            $this->forge->addColumn('characters', [
                'visibility_level' => [
                    'type' => 'VARCHAR',
                    'constraint' => 16,
                    'default' => 'none',
                    'after' => 'campaign_id',
                ],
            ]);
        }
    }

    private function createPermissions(): void
    {
        if ($this->db->tableExists('resource_permissions')) {
            return;
        }
        $this->forge->addField([
            'id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'resource_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'resource_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'access_level' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'none'],
            'granted_by_user_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey([
            'campaign_id', 'resource_type', 'resource_id', 'user_id',
        ]);
        $this->forge->addKey(['user_id', 'campaign_id']);
        $this->forge->addKey(['campaign_id', 'resource_type', 'resource_id']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('granted_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('resource_permissions');
    }

    private function backfillCharacterOwners(): void
    {
        if (!$this->db->tableExists('resource_permissions')
            || !$this->db->tableExists('characters')) {
            return;
        }
        $rows = $this->db->table('characters characters')
            ->select('characters.id, characters.campaign_id, characters.user_id')
            ->join('campaigns', 'campaigns.id = characters.campaign_id', 'inner')
            ->join('users', 'users.id = characters.user_id', 'inner')
            ->where('characters.campaign_id IS NOT NULL', null, false)
            ->where('characters.user_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        $table = $this->db->table('resource_permissions');
        foreach ($rows as $row) {
            $this->storeOwner($table, $row);
        }
        if (!$this->db->tableExists('shop_owner_claims')
            || !$this->db->fieldExists('character_id', 'shop_owner_claims')) {
            return;
        }
        $claims = $this->db->table('shop_owner_claims claims')
            ->select('claims.character_id AS id, claims.campaign_id, claims.user_id')
            ->join('characters', 'characters.id = claims.character_id '
                . 'AND characters.campaign_id = claims.campaign_id', 'inner')
            ->join('users', 'users.id = claims.user_id', 'inner')
            ->where('claims.character_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        foreach ($claims as $claim) {
            $this->storeOwner($table, $claim);
        }
    }

    private function storeOwner($table, array $row): void
    {
        $key = [
            'campaign_id' => (int) $row['campaign_id'],
            'resource_type' => 'character',
            'resource_id' => (int) $row['id'],
            'user_id' => (int) $row['user_id'],
        ];
        if ($table->where($key)->countAllResults()) {
            return;
        }
        $now = date('Y-m-d H:i:s');
        $table->insert($key + [
            'access_level' => 'owner',
            'granted_by_user_id' => (int) $row['user_id'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
