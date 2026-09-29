<?php

namespace App\Services\Admin;

use CodeIgniter\Database\BaseConnection;

/** Reads real module foreign keys; it intentionally does not mirror relations. */
final class MediaAssetRelationshipResolver
{
    private const RELATIONS = [
        ['table' => 'character_assets', 'module' => 'characters', 'label' => 'Character asset set'],
        ['table' => 'audio_tracks', 'module' => 'audio', 'label' => 'Audio track', 'audio' => true],
        ['table' => 'map_assets', 'module' => 'map-creator', 'label' => 'Map Creator asset', 'campaign' => 'campaign_id'],
        ['table' => 'handout_assets', 'module' => 'handouts', 'label' => 'Handout asset'],
        ['table' => 'compendium_assets', 'module' => 'compendium', 'label' => 'Compendium asset'],
        ['table' => 'compendium_corpus_assets', 'module' => 'compendium-corpus', 'label' => 'Compendium corpus asset'],
        ['table' => 'token_template_assets', 'module' => 'token-templates', 'label' => 'Token template asset'],
        ['table' => 'profession_assets', 'module' => 'professions', 'label' => 'Profession illustration'],
        ['table' => 'scene_media_assets', 'module' => 'scenes', 'label' => 'Scene asset', 'campaign' => 'campaign_id'],
        [
            'table' => 'scene_tiles', 'module' => 'scenes', 'label' => 'Scene tile',
            'campaign' => 'campaign_id', 'scene' => 'scene_id', 'name' => 'name', 'layer' => 'layer',
        ],
    ];

    private $db;

    public function __construct(BaseConnection $db)
    {
        $this->db = $db;
    }

    public function forAsset(int $assetId): array
    {
        $relations = [];
        foreach (self::RELATIONS as $definition) {
            $table = $definition['table'];
            if (!$this->db->tableExists($table) || !$this->db->fieldExists('media_asset_id', $table)) {
                continue;
            }
            if (!empty($definition['audio'])) {
                foreach ($this->audioRelations($assetId, $definition) as $relation) {
                    $relations[] = $relation;
                }
                continue;
            }
            $builder = $this->db->table($table)->select('id');
            if (!$this->db->fieldExists('id', $table)) {
                $builder = $this->db->table($table)->select('media_asset_id');
            }
            if (isset($definition['campaign']) && $this->db->fieldExists($definition['campaign'], $table)) {
                $builder->select($definition['campaign']);
            }
            foreach (['scene', 'name', 'layer'] as $field) {
                if (isset($definition[$field]) && $this->db->fieldExists($definition[$field], $table)) {
                    $builder->select($definition[$field]);
                }
            }
            if ($this->db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }
            $rows = $builder->where('media_asset_id', $assetId)->limit(101)->get()->getResultArray();
            foreach ($rows as $row) {
                $relations[] = [
                    'module' => $definition['module'],
                    'label' => $definition['label'],
                    'recordId' => isset($row['id']) ? (int) $row['id'] : null,
                    'campaignId' => isset($definition['campaign'], $row[$definition['campaign']])
                        ? (int) $row[$definition['campaign']] : null,
                    'sceneId' => isset($definition['scene'], $row[$definition['scene']])
                        ? (int) $row[$definition['scene']] : null,
                    'name' => isset($definition['name'], $row[$definition['name']])
                        ? (string) $row[$definition['name']] : null,
                    'layer' => isset($definition['layer'], $row[$definition['layer']])
                        ? (string) $row[$definition['layer']] : null,
                ];
            }
        }
        return $relations;
    }

    private function audioRelations(int $assetId, array $definition): array
    {
        $builder = $this->db->table('audio_tracks tracks')
            ->select(
                'tracks.id, tracks.library_id, tracks.owner_user_id, tracks.title, tracks.source_type, '
                . 'libraries.name AS library_name, libraries.scope AS library_scope, '
                . 'libraries.setting_id AS setting_id, libraries.system_id AS system_id, '
                . 'library_owners.username AS library_owner_username'
            )
            ->join('audio_libraries libraries', 'libraries.id = tracks.library_id', 'left')
            ->join('users library_owners', 'library_owners.id = libraries.owner_user_id', 'left')
            ->where('tracks.media_asset_id', $assetId)
            ->where('tracks.deleted_at', null)
            ->orderBy('tracks.id', 'ASC');
        $relations = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $relations[] = [
                'module' => $definition['module'],
                'label' => $definition['label'],
                'recordId' => (int) $row['id'],
                'campaignId' => null,
                'sceneId' => null,
                'name' => (string) ($row['title'] ?? ''),
                'layer' => null,
                'libraryId' => !empty($row['library_id']) ? (int) $row['library_id'] : null,
                'libraryName' => $row['library_name'] ?? null,
                'libraryScope' => $row['library_scope'] ?? null,
                'settingId' => !empty($row['setting_id']) ? (int) $row['setting_id'] : null,
                'systemId' => !empty($row['system_id']) ? (int) $row['system_id'] : null,
                'ownerUserId' => !empty($row['owner_user_id']) ? (int) $row['owner_user_id'] : null,
                'ownerUsername' => $row['library_owner_username'] ?? null,
                'sourceType' => $row['source_type'] ?? null,
            ];
        }
        return $relations;
    }

    /** @return int[] */
    public function assignedIds(): array
    {
        $ids = [];
        foreach (self::RELATIONS as $definition) {
            $table = $definition['table'];
            if (!$this->db->tableExists($table) || !$this->db->fieldExists('media_asset_id', $table)) {
                continue;
            }
            $builder = $this->db->table($table)->distinct()->select('media_asset_id')
                ->where('media_asset_id IS NOT NULL', null, false);
            if ($this->db->fieldExists('deleted_at', $table)) {
                $builder->where('deleted_at', null);
            }
            foreach ($builder->get()->getResultArray() as $row) {
                $ids[(int) $row['media_asset_id']] = true;
            }
        }
        return array_keys($ids);
    }
}
