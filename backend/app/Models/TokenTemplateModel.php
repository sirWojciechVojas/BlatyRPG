<?php

namespace App\Models;

use CodeIgniter\Model;

class TokenTemplateModel extends Model
{
    protected $table = 'token_templates';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'name', 'image_url', 'image_asset_id', 'width_cells', 'height_cells',
        'rotation', 'facing', 'rotation_handle_enabled', 'facing_handle_enabled',
        'rotation_follows_facing', 'show_info_unselected', 'resource_bar_position',
        'elevation', 'disposition', 'movement_range', 'movement_reset_mode',
        'bars_json', 'vision_json', 'revision', 'created_by_user_id',
        'updated_by_user_id',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
    protected $deletedField = 'deleted_at';
    protected $afterFind = ['normalizeRows'];
    protected $beforeInsert = ['encodeJson'];
    protected $beforeUpdate = ['encodeJson'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'image_asset_id', 'revision', 'created_by_user_id', 'updated_by_user_id'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            foreach (['width_cells', 'height_cells', 'rotation', 'facing', 'elevation', 'movement_range'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['rotation_handle_enabled', 'facing_handle_enabled', 'rotation_follows_facing', 'show_info_unselected'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
            foreach (['bars_json', 'vision_json'] as $field) {
                if (isset($row[$field]) && is_string($row[$field])) {
                    $decoded = json_decode($row[$field], true);
                    $row[$field] = is_array($decoded) ? $decoded : [];
                }
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else {
            $normalize($event['data']);
        }
        return $event;
    }

    protected function encodeJson(array $event): array
    {
        foreach (['bars_json', 'vision_json'] as $field) {
            if (isset($event['data'][$field]) && is_array($event['data'][$field])) {
                $event['data'][$field] = json_encode($event['data'][$field], JSON_UNESCAPED_UNICODE);
            }
        }
        return $event;
    }
}
