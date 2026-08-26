<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class ExtendSceneLighting extends Migration
{
    private const LIGHT_COLUMNS = [
        'opacity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
        'softness' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.5],
        'gradual_illumination' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'darkness_min' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0],
        'darkness_max' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 1],
        'source_type' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'light'],
        'provides_vision' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        'constrained_by_walls' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
        'animation' => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'none'],
        'animation_speed' => ['type' => 'DECIMAL', 'constraint' => '5,3', 'default' => 1],
        'animation_intensity' => ['type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.5],
        'elevation' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 0],
    ];

    public function up()
    {
        foreach (self::LIGHT_COLUMNS as $name => $definition) {
            if (!$this->db->fieldExists($name, 'scene_lights')) {
                $this->forge->addColumn('scene_lights', [$name => $definition]);
            }
        }
    }

    public function down()
    {
        foreach (array_reverse(array_keys(self::LIGHT_COLUMNS)) as $name) {
            if ($this->db->fieldExists($name, 'scene_lights')) {
                $this->forge->dropColumn('scene_lights', $name);
            }
        }
    }
}
