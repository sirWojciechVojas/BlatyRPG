<?php

namespace App\Services\Scene;

use CodeIgniter\Database\BaseConnection;

final class SceneContentDuplicator
{
    private const CONTENT_TABLES = [
        'scene_tokens',
        'scene_walls',
        'scene_lights',
        'scene_regions',
        'scene_tiles',
        'scene_fog_states',
    ];

    private $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function duplicate(int $campaignId, int $sourceSceneId, int $targetSceneId): void
    {
        $now = date('Y-m-d H:i:s');

        foreach (self::CONTENT_TABLES as $table) {
            if (!$this->db->tableExists($table)) {
                continue;
            }

            $source = $this->db->table($table)
                ->where('campaign_id', $campaignId)
                ->where('scene_id', $sourceSceneId);
            if ($this->db->fieldExists('deleted_at', $table)) {
                $source->where('deleted_at', null);
            }

            $rows = $source->get()->getResultArray();
            if ($rows === []) {
                continue;
            }

            $copies = array_map(
                static function (array $row) use ($targetSceneId, $now): array {
                    unset($row['id']);
                    $row['scene_id'] = $targetSceneId;
                    if (array_key_exists('revision', $row)) {
                        $row['revision'] = 1;
                    }
                    if (array_key_exists('created_at', $row)) {
                        $row['created_at'] = $now;
                    }
                    if (array_key_exists('updated_at', $row)) {
                        $row['updated_at'] = $now;
                    }
                    if (array_key_exists('deleted_at', $row)) {
                        $row['deleted_at'] = null;
                    }
                    return $row;
                },
                $rows
            );

            if ($this->db->table($table)->insertBatch($copies) === false) {
                throw new SceneException(
                    'scene_write_failed',
                    'Scene content could not be duplicated.',
                    500
                );
            }
        }
    }
}
