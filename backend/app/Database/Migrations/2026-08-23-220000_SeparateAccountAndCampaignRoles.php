<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class SeparateAccountAndCampaignRoles extends Migration
{
    public static function schemaContract(): array
    {
        return [
            'accountRoles' => ['user', 'admin'],
            'legacyAccountRoles' => ['player', 'gm'],
        ];
    }

    public function up()
    {
        if (!$this->hasRoleColumn()) {
            return;
        }
        $this->setRoleEnum(['user', 'player', 'gm', 'admin'], 'user');
        $this->db->table('users')->whereIn('role', ['player', 'gm'])
            ->update(['role' => 'user']);
        $this->setRoleEnum(['user', 'admin'], 'user');
    }

    public function down()
    {
        if (!$this->hasRoleColumn()) {
            return;
        }
        $this->setRoleEnum(['user', 'player', 'gm', 'admin'], 'player');
        $this->db->table('users')->where('role', 'user')
            ->update(['role' => 'player']);
        $this->setRoleEnum(['player', 'gm', 'admin'], 'player');
    }

    private function hasRoleColumn(): bool
    {
        return $this->db->tableExists('users')
            && $this->db->fieldExists('role', 'users');
    }

    private function setRoleEnum(array $roles, string $default): void
    {
        $table = $this->db->escapeIdentifiers($this->db->prefixTable('users'));
        $allowed = implode(', ', array_map([$this->db, 'escape'], $roles));
        $this->db->query(
            "ALTER TABLE {$table} MODIFY COLUMN role ENUM({$allowed}) "
            . "NOT NULL DEFAULT " . $this->db->escape($default)
        );
    }
}
