<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Global, administrator-managed token templates and immutable image assets. */
class CreateTokenTemplateLibrary extends Migration
{
    public function up()
    {
        $this->createAssets();
        $this->createTemplates();
        $this->extendSceneTokens();
    }

    public function down()
    {
        if ($this->db->tableExists('scene_tokens')) {
            foreach (['token_template_asset_id', 'token_template_id'] as $field) {
                if ($this->db->fieldExists($field, 'scene_tokens')) {
                    $this->forge->dropColumn('scene_tokens', $field);
                }
            }
        }
        $this->forge->dropTable('token_templates', true);
        $this->forge->dropTable('token_template_assets', true);
    }

    private function createAssets(): void
    {
        if ($this->db->tableExists('token_template_assets')) return;
        $this->forge->addField([
            'id' => $this->bigId(),
            'storage_key' => ['type' => 'VARCHAR', 'constraint' => 80],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'mime_type' => ['type' => 'VARCHAR', 'constraint' => 64],
            'byte_size' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'width' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'height' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('storage_key');
        $this->forge->addKey(['created_by_user_id', 'created_at']);
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('token_template_assets');
    }

    private function createTemplates(): void
    {
        if ($this->db->tableExists('token_templates')) return;
        $this->forge->addField([
            'id' => $this->bigId(),
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'image_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
            'image_asset_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'width_cells' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'default' => 1],
            'height_cells' => ['type' => 'DECIMAL', 'constraint' => '8,3', 'default' => 1],
            'rotation' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
            'facing' => ['type' => 'DECIMAL', 'constraint' => '7,3', 'default' => 0],
            'rotation_handle_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'facing_handle_enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'rotation_follows_facing' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'show_info_unselected' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'resource_bar_position' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'below'],
            'elevation' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 0],
            'disposition' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'neutral'],
            'movement_range' => ['type' => 'DECIMAL', 'constraint' => '10,3', 'default' => 6],
            'movement_reset_mode' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'turn'],
            'bars_json' => ['type' => 'JSON', 'null' => true],
            'vision_json' => ['type' => 'JSON', 'null' => true],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'updated_by_user_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => false],
            'updated_at' => ['type' => 'DATETIME', 'null' => false],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['deleted_at', 'name']);
        $this->forge->addKey('image_asset_id');
        $this->forge->addForeignKey('image_asset_id', 'token_template_assets', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('created_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->addForeignKey('updated_by_user_id', 'users', 'id', 'CASCADE', 'SET NULL');
        $this->forge->createTable('token_templates');
    }

    private function extendSceneTokens(): void
    {
        if (!$this->db->tableExists('scene_tokens')) return;
        if (!$this->db->fieldExists('token_template_id', 'scene_tokens')) {
            $this->forge->addColumn('scene_tokens', [
                'token_template_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                    'null' => true, 'after' => 'character_id',
                ],
            ]);
        }
        if (!$this->db->fieldExists('token_template_asset_id', 'scene_tokens')) {
            $this->forge->addColumn('scene_tokens', [
                'token_template_asset_id' => [
                    'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
                    'null' => true, 'after' => 'token_template_id',
                ],
            ]);
        }
    }

    private function bigId(): array
    {
        return [
            'type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true,
            'auto_increment' => true,
        ];
    }
}
