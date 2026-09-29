<?php

namespace App\Services\Tile;

use App\Models\SceneTileModel;
use App\Services\Media\ExternalMediaUrl;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use CodeIgniter\Database\BaseConnection;

final class SceneTileService
{
    private $db;
    private $tiles;
    private $scenes;
    private $access;
    private $validator;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneTileModel $tiles = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $access = null,
        ?TilePayloadValidator $validator = null,
        ?MediaService $media = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->tiles = $tiles ?: new SceneTileModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->access = $access ?: new SceneResourceAccessService();
        $this->validator = $validator ?: new TilePayloadValidator();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $rows = $this->tiles->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->orderBy('layer', 'ASC')
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        if (!$canManage) {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => !$row['hidden']));
        }
        return [
            'items' => array_map(static function (array $row) use ($canManage): array {
                return TilePresenter::present($row, $canManage);
            }, $rows),
            'sceneRevision' => (int) $scene['revision'],
            'capabilities' => ['canManage' => $canManage],
        ];
    }

    public function create(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->create($payload);
        $this->assertValid($validated);
        $this->assertGeometry($validated['data'], $scene);
        $data = $validated['data'] + [
            'campaign_id' => $campaignId, 'scene_id' => $sceneId, 'revision' => 1,
        ];
        $data = $this->attachCentralAsset($data, $auth, $campaignId);
        if (!$this->tiles->insert($data)) {
            throw new TileException('validation_failed', 'Tile could not be created.', 422, $this->tiles->errors());
        }
        return ['tile' => $this->present($campaignId, $sceneId, (int) $this->tiles->getInsertID())];
    }

    public function update(
        int $campaignId,
        int $sceneId,
        int $tileId,
        array $auth,
        array $payload
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $row = $this->find($campaignId, $sceneId, $tileId);
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $this->assertGeometry(array_merge($row, $validated['data']), $scene);
        $data = $validated['data'];
        if (array_key_exists('asset_url', $data)) {
            $data = $this->attachCentralAsset($data, $auth, $campaignId, $row);
        }
        $this->writeRevision(
            $campaignId,
            $sceneId,
            $tileId,
            $validated['revision'],
            $data
        );
        return ['tile' => $this->present($campaignId, $sceneId, $tileId)];
    }

    public function delete(int $campaignId, int $sceneId, int $tileId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $row = $this->find($campaignId, $sceneId, $tileId);
        $this->writeRevision($campaignId, $sceneId, $tileId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return ['deleted' => true, 'id' => $tileId, 'hidden' => (bool) $row['hidden']];
    }

    private function sceneContext(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new TileException(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details()
            );
        }
        return [$result['scene'], $result['capabilities']];
    }

    private function canManage(array $auth, int $campaignId, int $sceneId, array $caps): bool
    {
        return $this->access->canManage($auth, $campaignId, $sceneId, $caps);
    }

    private function assertManager(array $auth, int $campaignId, int $sceneId, array $caps): void
    {
        if (!$this->canManage($auth, $campaignId, $sceneId, $caps)) {
            throw new TileException('forbidden', 'You cannot manage tiles on this scene.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new TileException('validation_failed', 'Tile payload is invalid.', 422, $validated['errors']);
        }
    }

    private function assertGeometry(array $tile, array $scene): void
    {
        $outside = $tile['x'] < 0 || $tile['y'] < 0
            || $tile['x'] > $scene['width'] || $tile['y'] > $scene['height'];
        if ($outside) {
            throw new TileException('validation_failed', 'Tile geometry is invalid.', 422, [
                'geometry' => 'Tile origin must be inside the scene.',
            ]);
        }
    }

    private function find(int $campaignId, int $sceneId, int $tileId): array
    {
        $row = $this->tiles->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $tileId)->first();
        if (!$row) throw new TileException('tile_not_found', 'Tile was not found.', 404);
        return $row;
    }

    /**
     * Keeps the legacy asset_url contract intact while linking resolvable media
     * to the central registry. Relative legacy routes are only linked to their
     * existing campaign asset; arbitrary remote bytes are never downloaded.
     */
    private function attachCentralAsset(array $data, array $auth, int $campaignId, array $existing = []): array
    {
        if (!$this->db->fieldExists('media_asset_id', 'scene_tiles')) {
            return $data;
        }
        $url = (string) ($data['asset_url'] ?? '');
        $mediaAssetId = $this->internalAssetId($campaignId, $url);
        if ($mediaAssetId === null && ExternalMediaUrl::canonicalize($url) !== null) {
            try {
                $registered = $this->media->registerExternalUrl($auth, [
                    'sourceUrl' => $url,
                    'mediaType' => $data['media_type'] ?? $existing['media_type'] ?? 'image',
                    'category' => 'maps',
                    'name' => $data['name'] ?? $existing['name'] ?? 'Tile',
                ]);
                $mediaAssetId = (int) $registered['id'];
            } catch (MediaException $exception) {
                throw new TileException(
                    $exception->errorCode(),
                    $exception->getMessage(),
                    $exception->status(),
                    $exception->errors()
                );
            }
        }
        $data['media_asset_id'] = $mediaAssetId;
        return $data;
    }

    private function internalAssetId(int $campaignId, string $url): ?int
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (preg_match('#^/api/campaigns/(\d+)/maps/assets/(\d+)/file$#', $path, $matches) === 1
            && (int) $matches[1] === $campaignId && $this->db->tableExists('map_assets')) {
            $row = $this->db->table('map_assets')->select('media_asset_id')
                ->where('id', (int) $matches[2])->where('campaign_id', $campaignId)
                ->where('deleted_at', null)->get()->getRowArray();
            if (!empty($row['media_asset_id'])) return (int) $row['media_asset_id'];
        }
        if (preg_match('#^/api/campaigns/(\d+)/scene-assets/([a-f0-9]{32}\.(?:png|jpg|webp|gif))/file$#', $path, $matches) === 1
            && (int) $matches[1] === $campaignId && $this->db->tableExists('scene_media_assets')) {
            $row = $this->db->table('scene_media_assets')->select('media_asset_id')
                ->where('campaign_id', $campaignId)->where('legacy_key', $matches[2])->get()->getRowArray();
            if (!empty($row['media_asset_id'])) return (int) $row['media_asset_id'];
        }
        return null;
    }

    private function present(int $campaignId, int $sceneId, int $tileId): array
    {
        return TilePresenter::present($this->find($campaignId, $sceneId, $tileId), true);
    }

    private function writeRevision(
        int $campaignId,
        int $sceneId,
        int $tileId,
        int $revision,
        array $data
    ): void {
        $this->db->table('scene_tiles')->set($data)->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $tileId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $row = $this->find($campaignId, $sceneId, $tileId);
        throw new TileException('revision_conflict', 'Tile changed since it was loaded.', 409, [
            'currentRevision' => (int) $row['revision'],
        ]);
    }
}
