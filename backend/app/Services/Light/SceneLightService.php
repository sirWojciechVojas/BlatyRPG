<?php

namespace App\Services\Light;

use App\Models\SceneLightModel;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use CodeIgniter\Database\BaseConnection;

final class SceneLightService
{
    private $db;
    private $lights;
    private $scenes;
    private $access;
    private $validator;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneLightModel $lights = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $access = null,
        ?LightPayloadValidator $validator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->lights = $lights ?: new SceneLightModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->access = $access ?: new SceneResourceAccessService();
        $this->validator = $validator ?: new LightPayloadValidator();
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $rows = $this->lights->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->orderBy('id', 'ASC')->findAll();
        return [
            'items' => array_map(static function (array $row) use ($canManage): array {
                return LightPresenter::present($row, $canManage);
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
        if (!$this->lights->insert($data)) {
            throw new LightException(
                'validation_failed',
                'Light could not be created.',
                422,
                $this->lights->errors()
            );
        }
        return ['light' => $this->present($campaignId, $sceneId, (int) $this->lights->getInsertID())];
    }

    public function update(
        int $campaignId,
        int $sceneId,
        int $lightId,
        array $auth,
        array $payload
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $row = $this->find($campaignId, $sceneId, $lightId);
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $combined = array_merge($row, $validated['data']);
        $this->assertGeometry($combined, $scene);
        $this->writeRevision(
            $campaignId,
            $sceneId,
            $lightId,
            $validated['revision'],
            $validated['data']
        );
        return ['light' => $this->present($campaignId, $sceneId, $lightId)];
    }

    public function delete(int $campaignId, int $sceneId, int $lightId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($campaignId, $sceneId, $lightId);
        $this->writeRevision($campaignId, $sceneId, $lightId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return ['deleted' => true, 'id' => $lightId];
    }

    private function sceneContext(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new LightException(
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
            throw new LightException('forbidden', 'You cannot manage lights on this scene.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new LightException(
                'validation_failed',
                'Light payload is invalid.',
                422,
                $validated['errors']
            );
        }
    }

    private function assertGeometry(array $light, array $scene): void
    {
        $outside = $light['x'] < 0 || $light['y'] < 0
            || $light['x'] > $scene['width'] || $light['y'] > $scene['height'];
        if ($outside || $light['bright_radius'] > $light['dim_radius']) {
            throw new LightException('validation_failed', 'Light geometry is invalid.', 422, [
                'geometry' => 'Light must be inside the scene and its radii must be ordered.',
            ]);
        }
    }

    private function find(int $campaignId, int $sceneId, int $lightId): array
    {
        $row = $this->lights->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $lightId)->first();
        if (!$row) throw new LightException('light_not_found', 'Light was not found.', 404);
        return $row;
    }

    private function present(int $campaignId, int $sceneId, int $lightId): array
    {
        return LightPresenter::present($this->find($campaignId, $sceneId, $lightId), true);
    }

    private function writeRevision(
        int $campaignId,
        int $sceneId,
        int $lightId,
        int $revision,
        array $data
    ): void {
        $this->db->table('scene_lights')->set($data)->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $lightId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $row = $this->find($campaignId, $sceneId, $lightId);
        throw new LightException('revision_conflict', 'Light changed since it was loaded.', 409, [
            'currentRevision' => (int) $row['revision'],
        ]);
    }
}
