<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateOAuthAccounts extends Migration
{
    public function up()
    {
        $this->createIdentities();
        $this->createAuthorizationStates();
        $this->createLoginCodes();
    }

    public function down()
    {
        $this->forge->dropTable('oauth_login_codes', true);
        $this->forge->dropTable('oauth_authorization_states', true);
        $this->forge->dropTable('oauth_identities', true);
    }

    private function createIdentities(): void
    {
        if ($this->db->tableExists('oauth_identities')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 20],
            'provider_subject' => ['type' => 'VARCHAR', 'constraint' => 191],
            'provider_email' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['provider', 'provider_subject']);
        $this->forge->addUniqueKey(['user_id', 'provider']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('oauth_identities');
    }

    private function createAuthorizationStates(): void
    {
        if ($this->db->tableExists('oauth_authorization_states')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'state_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'browser_token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'provider' => ['type' => 'VARCHAR', 'constraint' => 20],
            'intent' => ['type' => 'VARCHAR', 'constraint' => 10],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('state_hash');
        $this->forge->addKey(['provider', 'consumed_at', 'expires_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('oauth_authorization_states');
    }

    private function createLoginCodes(): void
    {
        if ($this->db->tableExists('oauth_login_codes')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'code_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'expires_at' => ['type' => 'DATETIME'],
            'consumed_at' => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('code_hash');
        $this->forge->addKey(['user_id', 'consumed_at', 'expires_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('oauth_login_codes');
    }
}
