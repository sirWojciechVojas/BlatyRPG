<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneRegionModel extends Model
{
    protected $table = 'scene_regions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $useTimestamps = true;
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'name', 'polygons_json', 'darkness_mode',
        'darkness_value', 'disable_global_illumination', 'color', 'enabled',
        'hidden', 'revision',
    ];
    protected $afterFind = ['normalizeRows'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'scene_id', 'revision'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            $row['darkness_value'] = (float) ($row['darkness_value'] ?? 0);
            foreach (['disable_global_illumination', 'enabled', 'hidden'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
            if (is_string($row['polygons_json'] ?? null)) {
                $decoded = json_decode($row['polygons_json'], true);
                $row['polygons_json'] = is_array($decoded) ? $decoded : [];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
