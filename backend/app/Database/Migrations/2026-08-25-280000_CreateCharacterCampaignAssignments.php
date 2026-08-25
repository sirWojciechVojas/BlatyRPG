<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCharacterCampaignAssignments extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('character_campaigns')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                    'auto_increment' => true,
                ],
                'character_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                ],
                'campaign_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                ],
                'assigned_by_user_id' => [
                    'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['character_id', 'campaign_id']);
            $this->forge->addKey(['campaign_id', 'character_id']);
            $this->forge->addForeignKey(
                'character_id', 'characters', 'id', 'CASCADE', 'CASCADE'
            );
            $this->forge->addForeignKey(
                'campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE'
            );
            $this->forge->addForeignKey(
                'assigned_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL'
            );
            $this->forge->createTable('character_campaigns');
        }
        $this->backfillLegacyAssignments();
    }

    public function down()
    {
        $this->forge->dropTable('character_campaigns', true);
    }

    private function backfillLegacyAssignments(): void
    {
        if (!$this->db->tableExists('characters')) {
            return;
        }
        $rows = $this->db->table('characters characters')
            ->select('characters.id AS character_id, characters.campaign_id')
            ->join('campaigns campaigns', 'campaigns.id = characters.campaign_id', 'inner')
            ->where('characters.campaign_id IS NOT NULL', null, false)
            ->get()->getResultArray();
        $table = $this->db->table('character_campaigns');
        foreach ($rows as $row) {
            $key = [
                'character_id' => (int) $row['character_id'],
                'campaign_id' => (int) $row['campaign_id'],
            ];
            if ($table->where($key)->countAllResults()) {
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $table->insert($key + ['created_at' => $now, 'updated_at' => $now]);
        }
    }
}

