<?php

namespace App\Models;

use CodeIgniter\Model;

class TokenMovementRequestModel extends Model
{
    protected $table = 'token_movement_requests';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'campaign_id', 'scene_id', 'token_id', 'requested_by_user_id',
        'resolved_by_user_id', 'origin_x', 'origin_y', 'target_x', 'target_y',
        'waypoints_json', 'cost', 'spent_at_request', 'range_at_request',
        'token_revision', 'status', 'resolved_at',
    ];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $beforeInsert = ['encodeWaypoints'];
    protected $beforeUpdate = ['encodeWaypoints'];
    protected $afterFind = ['normalizeRows'];

    protected function encodeWaypoints(array $event): array
    {
        if (isset($event['data']['waypoints_json'])
            && is_array($event['data']['waypoints_json'])) {
            $event['data']['waypoints_json'] = json_encode(
                $event['data']['waypoints_json'], JSON_UNESCAPED_UNICODE
            );
        }
        return $event;
    }

    protected function normalizeRows(array $event): array
    {
        if (!isset($event['data'])) return $event;
        $normalize = static function (&$row): void {
            if (!is_array($row)) return;
            foreach (['id', 'campaign_id', 'scene_id', 'token_id',
                'requested_by_user_id', 'resolved_by_user_id', 'token_revision'] as $field) {
                if ($row[$field] !== null) $row[$field] = (int) $row[$field];
            }
            foreach (['origin_x', 'origin_y', 'target_x', 'target_y', 'cost',
                'spent_at_request', 'range_at_request'] as $field) {
                $row[$field] = (float) $row[$field];
            }
            if (is_string($row['waypoints_json'] ?? null)) {
                $decoded = json_decode($row['waypoints_json'], true);
                $row['waypoints_json'] = is_array($decoded) ? $decoded : [];
            }
        };
        if (($event['singleton'] ?? false) === false) {
            foreach ($event['data'] as &$row) $normalize($row);
        } else {
            $normalize($event['data']);
        }
        return $event;
    }
}
