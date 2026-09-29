<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneModel extends Model
{
    protected $table = 'scenes';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'campaign_id', 'name', 'description', 'background_url', 'width', 'height', 'padding',
        'background_color', 'darkness_level', 'global_illumination', 'global_light_level',
        'fog_exploration', 'fog_enabled', 'dynamic_vision', 'exploration_memory',
        'fog_unexplored_color', 'fog_unexplored_opacity', 'fog_explored_opacity',
        'fog_edge_softness', 'fog_update_during_drag',
        'global_illumination_threshold', 'fog_exploration_mode',
        'fog_explored_color', 'fog_exploration_image',
        'darkness_transition_from', 'darkness_transition_to',
        'darkness_transition_started_at', 'darkness_transition_duration',
        'grid_type', 'grid_size', 'grid_distance', 'grid_unit',
        'grid_offset_x', 'grid_offset_y', 'grid_color', 'grid_opacity', 'is_visible',
        'sort_order', 'revision',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $afterFind = ['normalizeRows'];

    protected function normalizeRows(array $data): array
    {
        if (!isset($data['data'])) {
            return $data;
        }
        $normalize = static function (&$row): void {
            if (!is_array($row)) {
                return;
            }
            foreach (['id', 'campaign_id', 'width', 'height', 'padding', 'grid_size', 'sort_order', 'revision'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (int) $row[$field];
                }
            }
            foreach (['darkness_level', 'global_light_level', 'grid_distance',
                'grid_offset_x', 'grid_offset_y', 'grid_opacity', 'fog_unexplored_opacity',
                'fog_explored_opacity', 'fog_edge_softness',
                'global_illumination_threshold', 'darkness_transition_from',
                'darkness_transition_to'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (float) $row[$field];
                }
            }
            foreach (['is_visible', 'global_illumination', 'fog_exploration', 'fog_enabled',
                'dynamic_vision', 'exploration_memory', 'fog_update_during_drag'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
        };
        if (($data['singleton'] ?? false) === false && is_array($data['data'])) {
            foreach ($data['data'] as &$row) {
                $normalize($row);
            }
        } else {
            $normalize($data['data']);
        }
        return $data;
    }
}
