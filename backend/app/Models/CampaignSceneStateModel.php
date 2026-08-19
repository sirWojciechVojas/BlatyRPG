<?php

namespace App\Models;

use CodeIgniter\Model;

class CampaignSceneStateModel extends Model
{
    protected $table = 'campaign_scene_state';
    protected $primaryKey = 'campaign_id';
    protected $useAutoIncrement = false;
    protected $returnType = 'array';
    protected $allowedFields = ['campaign_id', 'active_scene_id', 'revision', 'updated_by'];
    protected $useTimestamps = true;
    protected $dateFormat = 'datetime';
    protected $createdField = 'created_at';
    protected $updatedField = 'updated_at';
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
            foreach (['campaign_id', 'active_scene_id', 'revision', 'updated_by'] as $field) {
                if (isset($row[$field])) {
                    $row[$field] = (int) $row[$field];
                }
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
