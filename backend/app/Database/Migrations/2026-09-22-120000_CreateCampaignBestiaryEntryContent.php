<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateCampaignBestiaryEntryContent extends Migration
{
    public function up()
    {
        if (!$this->db->tableExists('campaign_bestiary_entry_content')) {
            $this->forge->addField([
                'id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                    'auto_increment' => true,
                ],
                'campaign_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                ],
                'entry_id' => [
                    'type' => 'BIGINT',
                    'constraint' => 20,
                    'unsigned' => true,
                ],
                'section_keys_json' => [
                    'type' => 'LONGTEXT',
                    'null' => false,
                ],
                'updated_by_user_id' => [
                    'type' => 'INT',
                    'constraint' => 10,
                    'unsigned' => true,
                    'null' => true,
                ],
                'created_at' => ['type' => 'DATETIME', 'null' => false],
                'updated_at' => ['type' => 'DATETIME', 'null' => false],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(
                ['campaign_id', 'entry_id'],
                'uq_campaign_bestiary_entry_content'
            );
            $this->forge->addForeignKey(
                'campaign_id',
                'campaigns',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->forge->addForeignKey(
                'entry_id',
                'compendium_entries',
                'id',
                'CASCADE',
                'CASCADE'
            );
            $this->forge->addForeignKey(
                'updated_by_user_id',
                'users',
                'id',
                'CASCADE',
                'SET NULL'
            );
            $this->forge->createTable('campaign_bestiary_entry_content');
        }

        $this->backfillSelections();
    }

    public function down()
    {
        $this->forge->dropTable('campaign_bestiary_entry_content', true);
    }

    private function backfillSelections(): void
    {
        if (
            !$this->db->tableExists('compendium_campaign_reveals')
            || !$this->db->tableExists('compendium_entities')
            || !$this->db->fieldExists(
                'character_id',
                'compendium_campaign_reveals'
            )
        ) {
            return;
        }

        $reveals = $this->db
            ->table('compendium_campaign_reveals reveal_row')
            ->select(
                'reveal_row.id, reveal_row.campaign_id, entity.entry_id, '
                . 'reveal_row.section_keys_json, '
                . 'reveal_row.granted_by_user_id, '
                . 'reveal_row.created_at, reveal_row.updated_at'
            )
            ->join(
                'compendium_entities entity',
                'entity.id=reveal_row.entity_id AND entity.deleted_at IS NULL',
                'inner'
            )
            ->where('reveal_row.character_id IS NOT NULL', null, false)
            ->where('reveal_row.revoked_at', null)
            ->orderBy('reveal_row.updated_at', 'ASC')
            ->get()
            ->getResultArray();
        if (!$reveals) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $groups = [];
        foreach ($reveals as $reveal) {
            $key = (int) $reveal['campaign_id'] . ':'
                . (int) $reveal['entry_id'];
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'campaign_id' => (int) $reveal['campaign_id'],
                    'entry_id' => (int) $reveal['entry_id'],
                    'section_keys' => [],
                    'updated_by_user_id' => null,
                    'created_at' => $reveal['created_at'] ?: $now,
                    'updated_at' => $reveal['updated_at'] ?: $now,
                ];
            }
            $decoded = json_decode(
                (string) ($reveal['section_keys_json'] ?? ''),
                true
            );
            if (is_array($decoded)) {
                $groups[$key]['section_keys'] = array_values(array_unique(
                    array_merge(
                        $groups[$key]['section_keys'],
                        array_map('strval', $decoded)
                    )
                ));
            }
            if (!empty($reveal['granted_by_user_id'])) {
                $groups[$key]['updated_by_user_id'] =
                    (int) $reveal['granted_by_user_id'];
            }
            $groups[$key]['updated_at'] = $reveal['updated_at'] ?: $now;
        }

        foreach ($groups as $group) {
            $encoded = json_encode(
                $group['section_keys'],
                JSON_UNESCAPED_UNICODE
            );
            $this->db->table('campaign_bestiary_entry_content')
                ->ignore(true)
                ->insert([
                    'campaign_id' => $group['campaign_id'],
                    'entry_id' => $group['entry_id'],
                    'section_keys_json' => $encoded,
                    'updated_by_user_id' => $group['updated_by_user_id'],
                    'created_at' => $group['created_at'],
                    'updated_at' => $group['updated_at'],
                ]);
            $this->db->table('compendium_campaign_reveals')
                ->where('campaign_id', $group['campaign_id'])
                ->whereIn(
                    'id',
                    array_map(
                        'intval',
                        array_column(
                            array_filter(
                                $reveals,
                                static fn (array $row): bool =>
                                    (int) $row['campaign_id']
                                        === $group['campaign_id']
                                    && (int) $row['entry_id']
                                        === $group['entry_id']
                            ),
                            'id'
                        )
                    )
                )
                ->update([
                    'section_keys_json' => $encoded,
                    'updated_at' => $now,
                ]);
        }
    }
}
