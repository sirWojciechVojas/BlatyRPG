<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneTileModel extends Model
{
    protected $table = 'scene_tiles';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'name', 'asset_url', 'media_type', 'layer',
        'x', 'y', 'width', 'height', 'rotation', 'opacity', 'sort_order',
        'hidden', 'locked', 'autoplay', 'loop', 'muted', 'revision',
    ];
    protected $useTimestamps = true;
    protected $afterFind = ['normalizeRows'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'scene_id', 'sort_order', 'revision'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            foreach (['x', 'y', 'width', 'height', 'rotation', 'opacity'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['hidden', 'locked', 'autoplay', 'loop', 'muted'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }
}
