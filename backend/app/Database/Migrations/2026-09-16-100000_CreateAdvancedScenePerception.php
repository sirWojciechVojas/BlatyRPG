<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Adds the extensible perception model without rewriting historical migrations. */
class CreateAdvancedScenePerception extends Migration
{
    private const WALL_COLUMNS = [
        'wall_type' => ['type' => 'VARCHAR', 'constraint' => 24, 'default' => 'solid'],
        'door_type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'none'],
        'restriction_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'normal'],
        'blocks_sound' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'proximity_threshold' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 10],
        'player_operable' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'sound_config_json' => ['type' => 'JSON', 'null' => true],
        'animation_config_json' => ['type' => 'JSON', 'null' => true],
    ];

    private const LIGHT_COLUMNS = [
        'animation_reverse' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'brightness' => ['type' => 'DECIMAL', 'constraint' => '5,3', 'default' => 1],
        'saturation' => ['type' => 'DECIMAL', 'constraint' => '5,3', 'default' => 1],
        'contrast' => ['type' => 'DECIMAL', 'constraint' => '5,3', 'default' => 1],
        'edge_softness' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.5],
        'transition_ratio' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.5],
        'asset_url' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
    ];

    private const SCENE_COLUMNS = [
        'global_illumination_threshold' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
        'fog_exploration_mode' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'individual'],
        'fog_explored_color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#202733'],
        'fog_exploration_image' => ['type' => 'VARCHAR', 'constraint' => 2048, 'null' => true],
        'darkness_transition_from' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'null' => true],
        'darkness_transition_to' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'null' => true],
        'darkness_transition_started_at' => ['type' => 'DATETIME', 'constraint' => 3, 'null' => true],
        'darkness_transition_duration' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 0],
    ];

    public function up()
    {
        $this->addMissingColumns('scene_walls', self::WALL_COLUMNS);
        $this->addMissingColumns('scene_lights', self::LIGHT_COLUMNS);
        $this->addMissingColumns('scenes', self::SCENE_COLUMNS);

        if ($this->db->tableExists('scene_walls')) {
            $this->db->query(
                "UPDATE scene_walls SET door_type = CASE "
                . "WHEN type='door' THEN 'door' WHEN type='secret' THEN 'secret' "
                . "WHEN type='window' THEN 'window' ELSE door_type END"
            );
            $this->db->query(
                "UPDATE scene_walls SET wall_type = CASE "
                . "WHEN blocks_movement=1 AND blocks_sight=0 AND blocks_light=0 THEN 'invisible' "
                . "WHEN blocks_movement=0 AND blocks_sight=1 AND blocks_light=1 THEN 'ethereal' "
                . "ELSE wall_type END"
            );
        }

        if ($this->db->tableExists('scene_regions')) return;
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'campaign_id' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
            'scene_id' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 150],
            'polygons_json' => ['type' => 'JSON'],
            'darkness_mode' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'override'],
            'darkness_value' => ['type' => 'DECIMAL', 'constraint' => '5,3', 'default' => 0],
            'disable_global_illumination' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'color' => ['type' => 'VARCHAR', 'constraint' => 9, 'default' => '#8B5CF6'],
            'enabled' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'hidden' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'revision' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['scene_id', 'id']);
        $this->forge->addKey(['campaign_id', 'scene_id', 'updated_at']);
        $this->forge->addForeignKey('campaign_id', 'campaigns', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('scene_id', 'scenes', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('scene_regions');
    }

    public function down()
    {
        $this->forge->dropTable('scene_regions', true);
        $this->dropExistingColumns('scenes', self::SCENE_COLUMNS);
        $this->dropExistingColumns('scene_lights', self::LIGHT_COLUMNS);
        $this->dropExistingColumns('scene_walls', self::WALL_COLUMNS);
    }

    private function addMissingColumns(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) return;
        foreach ($columns as $name => $definition) {
            if (!$this->db->fieldExists($name, $table)) {
                $this->forge->addColumn($table, [$name => $definition]);
            }
        }
    }

    private function dropExistingColumns(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) return;
        foreach (array_reverse(array_keys($columns)) as $name) {
            if ($this->db->fieldExists($name, $table)) $this->forge->dropColumn($table, $name);
        }
    }
}
