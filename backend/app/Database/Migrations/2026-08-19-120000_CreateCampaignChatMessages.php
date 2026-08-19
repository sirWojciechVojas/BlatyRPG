<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCampaignChatMessages extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('campaign_chat_messages')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type' => 'BIGINT',
                'constraint' => 20,
                'unsigned' => true,
                'auto_increment' => true,
            ],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'author_user_id' => [
                'type' => 'INT',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true,
            ],
            'author_name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'body' => ['type' => 'TEXT'],
            'message_type' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'text'],
            'client_nonce' => ['type' => 'CHAR', 'constraint' => 36, 'null' => true],
            'metadata_json' => ['type' => 'JSON', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['campaign_id', 'id']);
        $this->forge->addKey(['campaign_id', 'author_user_id', 'created_at']);
        $this->forge->addUniqueKey(['campaign_id', 'author_user_id', 'client_nonce']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('author_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('campaign_chat_messages');
    }

    public function down()
    {
        $this->forge->dropTable('campaign_chat_messages', true);
    }
}
