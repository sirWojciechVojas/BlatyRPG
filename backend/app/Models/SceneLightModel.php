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
        'color', 'intensity', 'enabled', 'hidden', 'revision',
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
            foreach (['x', 'y', 'bright_radius', 'dim_radius', 'intensity'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['enabled', 'hidden'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
