<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneTokenModel extends Model
{
    protected $table = 'scene_tokens';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useSoftDeletes = true;
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'character_id', 'name', 'image_url',
        'x', 'y', 'width', 'height', 'rotation', 'facing', 'elevation', 'disposition',
        'hidden', 'locked', 'bars_json', 'statuses_json', 'vision_json',
        'light_json', 'sort_order', 'revision',
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
            foreach (['id', 'campaign_id', 'scene_id', 'character_id', 'sort_order', 'revision'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            foreach (['x', 'y', 'width', 'height', 'rotation', 'facing', 'elevation'] as $field) {
                if (isset($row[$field])) $row[$field] = (float) $row[$field];
            }
            foreach (['hidden', 'locked'] as $field) {
                if (isset($row[$field])) $row[$field] = (bool) $row[$field];
            }
            foreach (self::jsonFields() as $field) {
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
        foreach (self::jsonFields() as $field) {
            if (isset($event['data'][$field]) && is_array($event['data'][$field])) {
                $event['data'][$field] = json_encode($event['data'][$field], JSON_UNESCAPED_UNICODE);
            }
        }
        return $event;
    }

    private static function jsonFields(): array
    {
        return ['bars_json', 'statuses_json', 'vision_json', 'light_json'];
    }
}
