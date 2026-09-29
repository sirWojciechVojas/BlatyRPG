<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCharacterBestiaryKnowledge extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('character_bestiary_knowledge')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            ],
            'character_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
            ],
            'entry_id' => [
                'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            ],
            'knowledge_level' => [
                'type' => 'VARCHAR', 'constraint' => 16,
                'default' => 'unknown',
            ],
            'updated_by_user_id' => [
                'type' => 'INT', 'constraint' => 10, 'unsigned' => true,
                'null' => true,
            ],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(
            ['campaign_id', 'character_id', 'entry_id'],
            'uq_character_bestiary_knowledge'
        );
        $this->forge->addKey(
            ['campaign_id', 'entry_id', 'knowledge_level'],
            false,
            false,
            'idx_character_bestiary_knowledge_entry'
        );
        $this->forge->addForeignKey(
            'campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'character_id', 'characters', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'entry_id', 'compendium_entries', 'id', 'CASCADE', 'CASCADE'
        );
        $this->forge->addForeignKey(
            'updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL'
        );
        $this->forge->createTable('character_bestiary_knowledge');

        $this->backfillExistingKnowledge();
    }

    public function down()
    {
        $this->forge->dropTable('character_bestiary_knowledge', true);
    }

    private function backfillExistingKnowledge(): void
    {
        if (
            !$this->db->tableExists('compendium_campaign_reveals')
            || !$this->db->tableExists('character_bestiary_encounters')
            || !$this->db->fieldExists(
                'character_id',
                'compendium_campaign_reveals'
            )
        ) {
            return;
        }

        $rows = $this->db->table('compendium_campaign_reveals reveal_row')
            ->select(
                'reveal_row.campaign_id, reveal_row.character_id, '
                . 'entity.entry_id, reveal_row.granted_by_user_id, '
                . 'reveal_row.created_at, reveal_row.updated_at, '
                . 'encounter.id AS encounter_id'
            )
            ->join(
                'compendium_entities entity',
                'entity.id=reveal_row.entity_id AND entity.deleted_at IS NULL',
                'inner'
            )
            ->join(
                'character_bestiary_encounters encounter',
                'encounter.campaign_id=reveal_row.campaign_id '
                . 'AND encounter.character_id=reveal_row.character_id '
                . 'AND encounter.entry_id=entity.entry_id',
                'left'
            )
            ->where('reveal_row.revoked_at', null)
            ->where('reveal_row.character_id IS NOT NULL', null, false)
            ->get()
            ->getResultArray();
        if (!$rows) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $knowledge = [];
        foreach ($rows as $row) {
            $key = (int) $row['campaign_id'] . ':'
                . (int) $row['character_id'] . ':'
                . (int) $row['entry_id'];
            $level = $row['encounter_id'] === null ? 'summary' : 'full';
            if (
                isset($knowledge[$key])
                && $knowledge[$key]['knowledge_level'] === 'full'
            ) {
                continue;
            }
            $knowledge[$key] = [
                'campaign_id' => (int) $row['campaign_id'],
                'character_id' => (int) $row['character_id'],
                'entry_id' => (int) $row['entry_id'],
                'knowledge_level' => $level,
                'updated_by_user_id' => $row['granted_by_user_id']
                    ? (int) $row['granted_by_user_id']
                    : null,
                'created_at' => $row['created_at'] ?: $now,
                'updated_at' => $row['updated_at'] ?: $now,
            ];
        }
        if ($knowledge) {
            $this->db->table('character_bestiary_knowledge')
                ->insertBatch(array_values($knowledge));
        }
    }
}
