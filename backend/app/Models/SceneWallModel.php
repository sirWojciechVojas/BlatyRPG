<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneWallModel extends Model
{
    protected $table = 'scene_walls';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'name', 'type', 'x1', 'y1', 'x2', 'y2',
        'blocks_movement', 'blocks_sight', 'blocks_light', 'door_state', 'color',
        'enabled', 'hidden', 'revision',
        'wall_type', 'door_type', 'restriction_type', 'blocks_sound',
        'proximity_threshold', 'player_operable', 'sound_config_json',
        'animation_config_json', 'source_map_id', 'source_map_revision',
        'source_map_object_id',
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
            foreach (['x1', 'y1', 'x2', 'y2', 'proximity_threshold'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['blocks_movement', 'blocks_sight', 'blocks_light', 'blocks_sound',
                'player_operable', 'enabled', 'hidden'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
            foreach (['sound_config_json', 'animation_config_json'] as $field) {
                if (!is_string($row[$field] ?? null)) continue;
                $decoded = json_decode($row[$field], true);
                $row[$field] = is_array($decoded) ? $decoded : [];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
