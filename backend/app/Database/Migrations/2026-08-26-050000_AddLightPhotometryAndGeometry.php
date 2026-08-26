<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddLightPhotometryAndGeometry extends Migration
{
    private const LIGHT_FIELDS = [
        'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'default' => 'Light'],
        'lumens' => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'default' => 800],
        'direction' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 0],
        'angle' => ['type' => 'DECIMAL', 'constraint' => '6,2', 'default' => 90],
        'area_width' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 400],
        'area_height' => ['type' => 'DECIMAL', 'constraint' => '12,3', 'default' => 400],
    ];

    public function up()
    {
        foreach (self::LIGHT_FIELDS as $name => $definition) {
            if (!$this->db->fieldExists($name, 'scene_lights')) {
                $this->forge->addColumn('scene_lights', [$name => $definition]);
            }
        }
        if (!$this->db->fieldExists('global_light_level', 'scenes')) {
            $this->forge->addColumn('scenes', [
                'global_light_level' => [
                    'type' => 'DECIMAL', 'constraint' => '4,3', 'default' => 0.8,
                ],
            ]);
        }
        $this->db->query(
            'UPDATE scene_lights SET lumens = ROUND(GREATEST(0, intensity) * 800), '
            . "source_type = CASE WHEN source_type = 'light' THEN 'omni' ELSE source_type END"
        );
        $this->db->query(
            'UPDATE scenes SET global_light_level = '
            . 'GREATEST(0, LEAST(1, 1 - darkness_level * '
            . 'CASE WHEN global_illumination = 1 THEN 0.18 ELSE 1 END))'
        );
    }

    public function down()
    {
        $this->db->query(
            "UPDATE scene_lights SET source_type = 'light' "
            . "WHERE source_type IN ('omni', 'directional', 'cone', 'area')"
        );
        foreach (array_reverse(array_keys(self::LIGHT_FIELDS)) as $name) {
            if ($this->db->fieldExists($name, 'scene_lights')) {
                $this->forge->dropColumn('scene_lights', $name);
            }
        }
        if ($this->db->fieldExists('global_light_level', 'scenes')) {
            $this->forge->dropColumn('scenes', 'global_light_level');
        }
    }
}
