<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class AddCharacterTargetsToCompendiumReveals extends Migration
{
    public function up()
    {
        if (
            !$this->db->tableExists('compendium_campaign_reveals')
            || $this->db->fieldExists(
                'character_id',
                'compendium_campaign_reveals'
            )
        ) {
            return;
        }

        $this->forge->addColumn('compendium_campaign_reveals', [
            'character_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
                'after' => 'user_id',
            ],
        ]);
        $this->db->query(
            'ALTER TABLE compendium_campaign_reveals '
            . 'ADD INDEX idx_compendium_reveal_character '
            . '(campaign_id, entity_id, character_id, revoked_at), '
            . 'ADD CONSTRAINT fk_compendium_reveal_character '
            . 'FOREIGN KEY (character_id) REFERENCES characters(id) '
            . 'ON DELETE CASCADE ON UPDATE CASCADE'
        );
    }

    public function down()
    {
        if (
            !$this->db->tableExists('compendium_campaign_reveals')
            || !$this->db->fieldExists(
                'character_id',
                'compendium_campaign_reveals'
            )
        ) {
            return;
        }

        $this->db->query(
            'ALTER TABLE compendium_campaign_reveals '
            . 'DROP FOREIGN KEY fk_compendium_reveal_character, '
            . 'DROP INDEX idx_compendium_reveal_character'
        );
        $this->forge->dropColumn(
            'compendium_campaign_reveals',
            'character_id'
        );
    }
}
