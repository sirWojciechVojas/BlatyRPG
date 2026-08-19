<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAuthSessionsAndHardenRoles extends Migration
{
    public function up()
    {
        $this->hardenRoles();
        $this->createAuthSessions();
        $this->createPasswordResetTokens();
    }

    public function down()
    {
        $this->forge->dropTable('password_reset_tokens', true);
        $this->forge->dropTable('auth_sessions', true);
        if ($this->db->tableExists('users') && $this->db->fieldExists('role', 'users')) {
            $this->forge->modifyColumn('users', [
                'role' => [
                    'type' => 'ENUM',
                    'constraint' => ['user', 'player', 'gm', 'admin'],
                    'default' => 'user',
                ],
            ]);
            $this->db->table('users')->where('role', 'player')->update(['role' => 'user']);
            $this->forge->modifyColumn('users', [
                'role' => [
                    'type' => 'ENUM',
                    'constraint' => ['user', 'gm', 'admin'],
                    'default' => 'user',
                ],
            ]);
        }
    }

    private function hardenRoles(): void
    {
        if (!$this->db->tableExists('users') || !$this->db->fieldExists('role', 'users')) {
            return;
        }
        $this->forge->modifyColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['user', 'player', 'gm', 'admin'],
                'default' => 'player',
            ],
        ]);
        $this->db->table('users')->where('role', 'user')->update(['role' => 'player']);
        $this->forge->modifyColumn('users', [
            'role' => [
                'type' => 'ENUM',
                'constraint' => ['player', 'gm', 'admin'],
                'default' => 'player',
            ],
        ]);
    }

    private function createAuthSessions(): void
    {
        if ($this->db->tableExists('auth_sessions')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'jti_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'revoked_at' => ['type' => 'DATETIME', 'null' => true],
            'last_seen_at' => ['type' => 'DATETIME', 'null' => true],
            'ip_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'user_agent_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('jti_hash');
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['user_id', 'revoked_at', 'expires_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('auth_sessions');
    }

    private function createPasswordResetTokens(): void
    {
        if ($this->db->tableExists('password_reset_tokens')) {
            return;
        }
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'token_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'expires_at' => ['type' => 'DATETIME'],
            'used_at' => ['type' => 'DATETIME', 'null' => true],
            'request_ip_hash' => ['type' => 'CHAR', 'constraint' => 64, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('token_hash');
        $this->forge->addKey(['user_id', 'used_at', 'expires_at']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('password_reset_tokens');
    }
}
