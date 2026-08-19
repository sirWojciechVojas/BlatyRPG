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
        'background_color', 'grid_type', 'grid_size', 'grid_distance', 'grid_unit',
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
            foreach (['grid_distance', 'grid_offset_x', 'grid_offset_y', 'grid_opacity'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (float) $row[$field];
                }
            }
            if (isset($row['is_visible'])) {
                $row['is_visible'] = (bool) $row['is_visible'];
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
