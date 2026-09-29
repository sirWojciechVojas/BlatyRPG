<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneLightModel extends Model
{
    protected $table = 'scene_lights';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'x', 'y', 'bright_radius', 'dim_radius',
        'color', 'intensity', 'opacity', 'softness', 'clarity',
        'gradual_illumination',
        'darkness_min', 'darkness_max', 'source_type', 'provides_vision',
        'constrained_by_walls', 'animation', 'animation_speed',
        'animation_intensity', 'elevation', 'enabled', 'hidden', 'revision',
        'name', 'lumens', 'direction', 'angle', 'area_width', 'area_height',
        'animation_reverse', 'brightness', 'saturation', 'contrast',
        'edge_softness', 'transition_ratio', 'asset_url',
        'source_map_id', 'source_map_revision', 'source_map_object_id',
    ];
    protected $useTimestamps = true;
    protected $afterFind = ['normalizeRows'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'scene_id', 'revision', 'source_map_id', 'source_map_revision'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            foreach (['x', 'y', 'bright_radius', 'dim_radius', 'intensity', 'opacity',
                'softness', 'clarity', 'darkness_min', 'darkness_max',
                'animation_speed',
                'animation_intensity', 'elevation', 'direction', 'angle',
                'area_width', 'area_height', 'brightness', 'saturation',
                'contrast', 'edge_softness', 'transition_ratio'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            if (isset($row['lumens'])) $row['lumens'] = (int) $row['lumens'];
            foreach (['gradual_illumination', 'provides_vision', 'constrained_by_walls',
                'animation_reverse', 'enabled', 'hidden'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
