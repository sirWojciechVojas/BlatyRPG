<?php

namespace App\Models;

use CodeIgniter\Model;

class SceneTokenSyncLinkModel extends Model
{
    protected $table = 'scene_token_sync_links';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'campaign_id', 'source_scene_id', 'source_token_id', 'target_scene_id',
        'target_token_id', 'enabled', 'diverged_fields_json', 'last_synced_at',
        'created_by',
    ];
    protected $afterFind = ['normalizeRows'];
    protected $beforeInsert = ['encodeFields'];
    protected $beforeUpdate = ['encodeFields'];

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'source_scene_id', 'source_token_id',
                'target_scene_id', 'target_token_id', 'created_by'] as $field) {
                if (isset($row[$field])) $row[$field] = (int) $row[$field];
            }
            $row['enabled'] = !empty($row['enabled']);
            if (is_string($row['diverged_fields_json'] ?? null)) {
                $decoded = json_decode($row['diverged_fields_json'], true);
                $row['diverged_fields_json'] = is_array($decoded) ? $decoded : [];
            }
            $row['diverged_fields_json'] ??= [];
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else $normalize($event['data']);
        return $event;
    }

    protected function encodeFields(array $event): array
    {
        if (isset($event['data']['diverged_fields_json'])
            && is_array($event['data']['diverged_fields_json'])) {
            $event['data']['diverged_fields_json'] = json_encode(
                array_values(array_unique($event['data']['diverged_fields_json'])),
                JSON_UNESCAPED_UNICODE
            );
        }
        return $event;
    }
}
