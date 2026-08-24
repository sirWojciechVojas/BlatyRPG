<?php

namespace App\Services\Wall;

use App\Models\SceneWallModel;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use CodeIgniter\Database\BaseConnection;

final class SceneWallService
{
    private $db;
    private $walls;
    private $scenes;
    private $access;
    private $validator;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneWallModel $walls = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $access = null,
        ?WallPayloadValidator $validator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->walls = $walls ?: new SceneWallModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->access = $access ?: new SceneResourceAccessService();
        $this->validator = $validator ?: new WallPayloadValidator();
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $rows = $this->walls->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->orderBy('id', 'ASC')->findAll();
        return [
            'items' => array_map(static function (array $row) use ($canManage): array {
                return WallPresenter::present($row, $canManage);
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
        $this->assertWithinScene($validated['data'], $scene);
        $data = $validated['data'] + [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'revision' => 1,
        ];
        if (!$this->walls->insert($data)) {
            throw new WallException(
                'validation_failed',
                'Wall could not be created.',
                422,
                $this->walls->errors()
            );
        }
        return ['wall' => $this->present($campaignId, $sceneId, (int) $this->walls->getInsertID())];
    }

    public function update(
        int $campaignId,
        int $sceneId,
        int $wallId,
        array $auth,
        array $payload
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $row = $this->find($campaignId, $sceneId, $wallId);
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $this->assertWithinScene(array_merge($row, $validated['data']), $scene);
        $this->writeRevision(
            $campaignId,
            $sceneId,
            $wallId,
            $validated['revision'],
            $validated['data']
        );
        return ['wall' => $this->present($campaignId, $sceneId, $wallId)];
    }

    public function delete(int $campaignId, int $sceneId, int $wallId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($campaignId, $sceneId, $wallId);
        $this->writeRevision($campaignId, $sceneId, $wallId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return ['deleted' => true, 'id' => $wallId];
    }

    private function sceneContext(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new WallException(
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
            throw new WallException('forbidden', 'You cannot manage walls on this scene.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new WallException(
                'validation_failed',
                'Wall payload is invalid.',
                422,
                $validated['errors']
            );
        }
    }

    private function assertWithinScene(array $wall, array $scene): void
    {
        $outside = $wall['x1'] < 0 || $wall['x2'] < 0 || $wall['y1'] < 0 || $wall['y2'] < 0
            || $wall['x1'] > $scene['width'] || $wall['x2'] > $scene['width']
            || $wall['y1'] > $scene['height'] || $wall['y2'] > $scene['height'];
        if ($outside) {
            throw new WallException('validation_failed', 'Wall must be inside the scene.', 422, [
                'geometry' => 'Wall coordinates exceed scene bounds.',
            ]);
        }
    }

    private function find(int $campaignId, int $sceneId, int $wallId): array
    {
        $row = $this->walls->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $wallId)->first();
        if (!$row) throw new WallException('wall_not_found', 'Wall was not found.', 404);
        return $row;
    }

    private function present(int $campaignId, int $sceneId, int $wallId): array
    {
        return WallPresenter::present($this->find($campaignId, $sceneId, $wallId), true);
    }

    private function writeRevision(
        int $campaignId,
        int $sceneId,
        int $wallId,
        int $revision,
        array $data
    ): void {
        $this->db->table('scene_walls')->set($data)->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $wallId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $row = $this->find($campaignId, $sceneId, $wallId);
        throw new WallException('revision_conflict', 'Wall changed since it was loaded.', 409, [
            'currentRevision' => (int) $row['revision'],
        ]);
    }
}
