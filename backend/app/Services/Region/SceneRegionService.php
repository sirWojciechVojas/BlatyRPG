<?php

namespace App\Services\Region;

use App\Models\SceneRegionModel;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use CodeIgniter\Database\BaseConnection;

final class SceneRegionService
{
    private $db;
    private $regions;
    private $scenes;
    private $access;
    private $validator;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneRegionModel $regions = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $access = null,
        ?RegionPayloadValidator $validator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->regions = $regions ?: new SceneRegionModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->access = $access ?: new SceneResourceAccessService();
        $this->validator = $validator ?: new RegionPayloadValidator();
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $rows = $this->regions->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->orderBy('id', 'ASC')->findAll();
        return [
            'items' => array_map(
                static fn (array $row): array => RegionPresenter::present($row, $canManage),
                array_values(array_filter($rows, static fn (array $row): bool =>
                    $canManage || empty($row['hidden'])))
            ),
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
        $this->assertWithinScene($validated['data'], $scene);
        $data = $validated['data'] + [
            'campaign_id' => $campaignId, 'scene_id' => $sceneId, 'revision' => 1,
        ];
        if (!$this->regions->insert($data)) {
            throw new RegionException('validation_failed', 'Region could not be created.', 422, $this->regions->errors());
        }
        return ['region' => $this->present($campaignId, $sceneId, (int) $this->regions->getInsertID())];
    }

    public function update(
        int $campaignId,
        int $sceneId,
        int $regionId,
        array $auth,
        array $payload
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $row = $this->find($campaignId, $sceneId, $regionId);
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $this->assertWithinScene(array_merge($row, $validated['data']), $scene);
        $this->writeRevision($campaignId, $sceneId, $regionId, $validated['revision'], $validated['data']);
        return ['region' => $this->present($campaignId, $sceneId, $regionId)];
    }

    public function delete(int $campaignId, int $sceneId, int $regionId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($campaignId, $sceneId, $regionId);
        $this->writeRevision($campaignId, $sceneId, $regionId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return ['deleted' => true, 'id' => $regionId];
    }

    private function sceneContext(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new RegionException(
                $exception->errorCode(), $exception->getMessage(),
                $exception->status(), $exception->details()
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
            throw new RegionException('forbidden', 'You cannot manage regions on this scene.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new RegionException('validation_failed', 'Region payload is invalid.', 422, $validated['errors']);
        }
    }

    private function assertWithinScene(array $data, array $scene): void
    {
        $polygons = $data['polygons_json'] ?? [];
        if (is_string($polygons)) $polygons = json_decode($polygons, true) ?: [];
        foreach ($polygons as $polygon) {
            foreach ($polygon as $point) {
                if ($point['x'] < 0 || $point['y'] < 0
                    || $point['x'] > (float) $scene['width']
                    || $point['y'] > (float) $scene['height']) {
                    throw new RegionException('validation_failed', 'Region must be inside the scene.', 422, [
                        'polygons' => 'A region point exceeds scene bounds.',
                    ]);
                }
            }
        }
    }

    private function find(int $campaignId, int $sceneId, int $regionId): array
    {
        $row = $this->regions->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $regionId)->first();
        if (!$row) throw new RegionException('region_not_found', 'Region was not found.', 404);
        return $row;
    }

    private function present(int $campaignId, int $sceneId, int $regionId): array
    {
        return RegionPresenter::present($this->find($campaignId, $sceneId, $regionId), true);
    }

    private function writeRevision(
        int $campaignId,
        int $sceneId,
        int $regionId,
        int $revision,
        array $data
    ): void {
        $this->db->table('scene_regions')->set($data)->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $regionId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $row = $this->find($campaignId, $sceneId, $regionId);
        throw new RegionException('revision_conflict', 'Region changed since it was loaded.', 409, [
            'currentRevision' => (int) $row['revision'],
        ]);
    }
}
