<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendCampaignMembership extends Migration
{
    public function up()
    {
        $this->addCampaignFields();
        $this->addMembershipFields();
        $this->createInvitations();
        $this->backfillCampaignMetadata();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_invitations', true);
        $this->dropColumns('campaign_members', ['joined_at', 'left_at']);
        $this->dropColumns('campaigns', [
            'status', 'banner_url', 'settings_json', 'last_activity_at',
        ]);
    }

    private function addCampaignFields(): void
    {
        if (!$this->db->tableExists('campaigns')) {
            return;
        }
        $fields = [
            'status' => [
                'type' => 'VARCHAR', 'constraint' => 24,
                'default' => 'active', 'after' => 'is_active',
            ],
            'banner_url' => [
                'type' => 'VARCHAR', 'constraint' => 2048,
                'null' => true, 'after' => 'description',
            ],
            'settings_json' => [
                'type' => 'JSON', 'null' => true, 'after' => 'system_type',
            ],
            'last_activity_at' => [
                'type' => 'DATETIME', 'null' => true, 'after' => 'updated_at',
            ],
        ];
        foreach ($fields as $name => $definition) {
            if (!$this->db->fieldExists($name, 'campaigns')) {
                $this->forge->addColumn('campaigns', [$name => $definition]);
            }
        }
    }

    private function addMembershipFields(): void
    {
        if (!$this->db->tableExists('campaign_members')) {
            return;
        }
        foreach (['joined_at', 'left_at'] as $field) {
            if (!$this->db->fieldExists($field, 'campaign_members')) {
                $this->forge->addColumn('campaign_members', [
                    $field => ['type' => 'DATETIME', 'null' => true],
                ]);
            }
        }
    }

    private function createInvitations(): void
    {
        if ($this->db->tableExists('campaign_invitations')) {
            return;
        }
        $this->forge->addField([
            'id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'invitee_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'invited_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'role' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'player'],
            'status' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'pending'],
            'pending_key' => ['type' => 'VARCHAR', 'constraint' => 64, 'null' => true],
            'message' => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'expires_at' => ['type' => 'DATETIME', 'null' => false],
            'responded_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('pending_key');
        $this->forge->addKey(['campaign_id', 'status']);
        $this->forge->addKey(['invitee_user_id', 'status']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('invitee_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('invited_by_user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('campaign_invitations');
    }

    private function backfillCampaignMetadata(): void
    {
        if ($this->db->tableExists('campaigns')
            && $this->db->fieldExists('status', 'campaigns')) {
            $table = $this->db->prefixTable('campaigns');
            $this->db->query(
                "UPDATE {$table} SET status = CASE WHEN is_active = 1 "
                . "THEN 'active' ELSE 'paused' END, last_activity_at = "
                . 'COALESCE(last_activity_at, updated_at, created_at)'
            );
        }
        if ($this->db->tableExists('campaign_members')
            && $this->db->fieldExists('joined_at', 'campaign_members')) {
            $table = $this->db->prefixTable('campaign_members');
            $this->db->query(
                "UPDATE {$table} SET joined_at = COALESCE(joined_at, created_at)"
            );
        }
    }

    private function dropColumns(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        foreach ($columns as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}
