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
    ];
    protected $useTimestamps = true;
    protected $afterFind = ['normalizeRows'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'scene_id', 'revision'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            foreach (['x1', 'y1', 'x2', 'y2'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['blocks_movement', 'blocks_sight', 'blocks_light', 'enabled', 'hidden'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
