<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixCampaignChatAuthorForeignKey extends Migration
{
    private const OLD_NAME = 'campaign_chat_messages_author_user_id_foreign';
    private const NEW_NAME = 'campaign_chat_messages_author_user_id_fk_v2';

    public function up()
    {
        if (!$this->db->tableExists('campaign_chat_messages')
            || strtolower((string) $this->db->DBDriver) !== 'mysqli') {
            return;
        }

        $old = $this->relation(self::OLD_NAME);
        if ($this->hasCorrectRules($old)) {
            return;
        }

        if (!$this->hasCorrectRules($this->relation(self::NEW_NAME))) {
            $this->db->query(
                'ALTER TABLE `campaign_chat_messages`
                 ADD CONSTRAINT `' . self::NEW_NAME . '`
                 FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`)
                 ON UPDATE CASCADE ON DELETE SET NULL'
            );
        }
        if ($old) {
            $this->db->query(
                'ALTER TABLE `campaign_chat_messages`
                 DROP FOREIGN KEY `' . self::OLD_NAME . '`'
            );
        }
    }

    public function down()
    {
        // Security correction is intentionally not reverted.
    }

    private function relation(string $name): ?array
    {
        return $this->db->query(
            'SELECT CONSTRAINT_NAME, UPDATE_RULE, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?',
            ['campaign_chat_messages', $name]
        )->getRowArray();
    }

    private function hasCorrectRules(?array $relation): bool
    {
        return $relation
            && strtoupper((string) $relation['UPDATE_RULE']) === 'CASCADE'
            && strtoupper((string) $relation['DELETE_RULE']) === 'SET NULL';
    }
}
