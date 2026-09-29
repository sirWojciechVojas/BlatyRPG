<?php

namespace App\Services\Scene;

use App\Models\CampaignSceneStateModel;
use App\Models\SceneModel;
use App\Services\Campaign\CampaignAccessService;
use CodeIgniter\Database\BaseConnection;

class SceneService
{
    private $db;
    private $scenes;
    private $state;
    private $access;
    private $resourceAccess;
    private $validator;
    private $contentDuplicator;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneModel $scenes = null,
        ?CampaignSceneStateModel $state = null,
        ?CampaignAccessService $access = null,
        ?ScenePayloadValidator $validator = null,
        ?SceneResourceAccessService $resourceAccess = null,
        ?SceneContentDuplicator $contentDuplicator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->scenes = $scenes ?: new SceneModel();
        $this->state = $state ?: new CampaignSceneStateModel();
        $this->access = $access ?: new CampaignAccessService();
        $this->validator = $validator ?: new ScenePayloadValidator();
        $this->resourceAccess = $resourceAccess ?: new SceneResourceAccessService();
        $this->contentDuplicator = $contentDuplicator ?: new SceneContentDuplicator($this->db);
    }

    public function listScenes(int $campaignId, array $auth): array
    {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $query = $this->scenes->where('campaign_id', $campaignId);
        $items = $query->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        if (!$capabilities['canViewHidden']) {
            $items = array_values(array_filter($items, function (array $scene) use (
                $auth,
                $campaignId,
                $capabilities
            ): bool {
                return $this->resourceAccess->canView(
                    $auth,
                    $campaignId,
                    $scene,
                    $capabilities
                );
            }));
        }
        $state = $this->state->find($campaignId);
        $activeSceneId = $state && $state['active_scene_id'] !== null
            ? (int) $state['active_scene_id']
            : null;
        if ($activeSceneId !== null && !in_array($activeSceneId, array_column($items, 'id'), true)) {
            $activeSceneId = null;
        }

        return compact('items', 'activeSceneId', 'capabilities');
    }

    public function getScene(int $campaignId, int $sceneId, array $auth): array
    {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $scene = $this->findScene($campaignId, $sceneId);
        if (!$scene || !$this->resourceAccess->canView(
            $auth,
            $campaignId,
            $scene,
            $capabilities
        )) {
            throw new SceneException('scene_not_found', 'Scene was not found.', 404);
        }
        return ['scene' => $scene, 'capabilities' => $capabilities];
    }

    public function createScene(int $campaignId, array $auth, array $payload): array
    {
        $capabilities = $this->authorize($campaignId, $auth, true);
        $validated = $this->validator->validateCreate($payload);
        $this->assertValid($validated);
        $data = array_merge($validated['data'], ['campaign_id' => $campaignId, 'revision' => 1]);
        if (!$this->scenes->insert($data)) {
            throw new SceneException('validation_failed', 'Scene could not be created.', 422, $this->scenes->errors());
        }
        $scene = $this->findScene($campaignId, (int) $this->scenes->getInsertID());
        return ['scene' => $scene, 'capabilities' => $capabilities];
    }

    public function duplicateScene(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $payload
    ): array {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $this->assertSceneManager($campaignId, $sceneId, $auth, $capabilities);
        $validated = $this->validator->validateDuplicate($payload);
        $this->assertValid($validated);

        $this->db->transBegin();
        try {
            $source = $this->db->table('scenes')
                ->where('campaign_id', $campaignId)
                ->where('id', $sceneId)
                ->where('deleted_at', null)
                ->get()->getRowArray();
            if (!$source) {
                throw new SceneException('scene_not_found', 'Scene was not found.', 404);
            }

            $data = $source;
            foreach (['id', 'created_at', 'updated_at', 'deleted_at'] as $field) {
                unset($data[$field]);
            }
            $now = date('Y-m-d H:i:s');
            $data['name'] = $validated['data']['name'];
            $data['sort_order'] = (int) ($source['sort_order'] ?? 0) + 1;
            $data['revision'] = 1;
            $data['created_at'] = $now;
            $data['updated_at'] = $now;

            if (!$this->db->table('scenes')->insert($data)) {
                throw new SceneException(
                    'scene_write_failed',
                    'Scene could not be duplicated.',
                    500
                );
            }
            $targetSceneId = (int) $this->db->insertID();
            $this->contentDuplicator->duplicate($campaignId, $sceneId, $targetSceneId);
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }

        return [
            'scene' => $this->findScene($campaignId, $targetSceneId),
            'capabilities' => $capabilities,
        ];
    }

    public function updateScene(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $this->assertSceneManager($campaignId, $sceneId, $auth, $capabilities);
        $validated = $this->validator->validateUpdate($payload);
        $this->assertValid($validated);
        $data = array_merge($validated['data'], ['updated_at' => date('Y-m-d H:i:s')]);
        $this->db->transBegin();
        try {
            $this->db->table('scenes')->set($data)->set('revision', 'revision + 1', false)
                ->where('id', $sceneId)->where('campaign_id', $campaignId)
                ->where('revision', $validated['revision'])->where('deleted_at', null)
                ->update();
            if ($this->db->affectedRows() !== 1) {
                $this->throwMissingOrConflict($campaignId, $sceneId);
            }
            if (array_key_exists('is_visible', $data) && !$data['is_visible']) {
                $now = date('Y-m-d H:i:s');
                $this->db->table('campaign_scene_state')->set('active_scene_id', null)
                    ->set('updated_by', (int) ($auth['user_id'] ?? 0) ?: null)
                    ->set('updated_at', $now)->set('revision', 'revision + 1', false)
                    ->where('campaign_id', $campaignId)
                    ->where('active_scene_id', $sceneId)->update();
            }
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['scene' => $this->findScene($campaignId, $sceneId), 'capabilities' => $capabilities];
    }

    public function transitionDarkness(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $payload
    ): array {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $this->assertSceneManager($campaignId, $sceneId, $auth, $capabilities);
        $scene = $this->findScene($campaignId, $sceneId);
        $target = filter_var($payload['target'] ?? null, FILTER_VALIDATE_FLOAT);
        $duration = filter_var($payload['duration'] ?? 1500, FILTER_VALIDATE_INT);
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($target === false || !is_finite((float) $target)
            || $target < 0 || $target > 1
            || $duration === false || $duration < 100 || $duration > 3600000
            || $revision === false) {
            throw new SceneException(
                'validation_failed',
                'Darkness transition payload is invalid.',
                422
            );
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $from = $this->currentDarkness($scene, $now);
        $data = [
            'darkness_level' => (float) $target,
            'global_light_level' => 1 - (float) $target,
            'darkness_transition_from' => $from,
            'darkness_transition_to' => (float) $target,
            'darkness_transition_started_at' => $now->format('Y-m-d H:i:s.v'),
            'darkness_transition_duration' => (int) $duration,
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ];
        $this->db->table('scenes')->set($data)->set('revision', 'revision + 1', false)
            ->where('id', $sceneId)->where('campaign_id', $campaignId)
            ->where('revision', (int) $revision)->where('deleted_at', null)
            ->update();
        if ($this->db->affectedRows() !== 1) {
            $this->throwMissingOrConflict($campaignId, $sceneId);
        }
        return [
            'scene' => $this->findScene($campaignId, $sceneId),
            'capabilities' => $capabilities,
            'serverTime' => $now->format(DATE_ATOM),
        ];
    }

    public function deleteScene(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        $capabilities = $this->authorize($campaignId, $auth, false);
        $this->assertSceneManager($campaignId, $sceneId, $auth, $capabilities);
        $validated = $this->validator->validateRevision($payload);
        $this->assertValid($validated);
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        try {
            $this->db->table('scenes')->set('deleted_at', $now)->set('updated_at', $now)
                ->set('revision', 'revision + 1', false)->where('id', $sceneId)
                ->where('campaign_id', $campaignId)->where('revision', $validated['revision'])
                ->where('deleted_at', null)->update();
            if ($this->db->affectedRows() !== 1) {
                $this->throwMissingOrConflict($campaignId, $sceneId);
            }
            $this->db->table('campaign_scene_state')->set('active_scene_id', null)
                ->set('updated_by', (int) ($auth['user_id'] ?? 0) ?: null)
                ->set('updated_at', $now)->set('revision', 'revision + 1', false)
                ->where('campaign_id', $campaignId)->where('active_scene_id', $sceneId)->update();
            if ($this->db->tableExists('scene_token_sync_links')) {
                $this->db->table('scene_token_sync_links')
                    ->where('campaign_id', $campaignId)
                    ->groupStart()->where('source_scene_id', $sceneId)
                    ->orWhere('target_scene_id', $sceneId)->groupEnd()->delete();
            }
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $state = $this->state->find($campaignId);
        $activeSceneId = $state && $state['active_scene_id'] !== null
            ? (int) $state['active_scene_id'] : null;
        return compact('activeSceneId', 'capabilities') + ['deleted' => true, 'id' => $sceneId];
    }

    public function activateScene(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        $capabilities = $this->authorize($campaignId, $auth, true);
        $validated = $this->validator->validateRevision($payload);
        $this->assertValid($validated);
        $scene = $this->findScene($campaignId, $sceneId);
        if (!$scene) {
            throw new SceneException('scene_not_found', 'Scene was not found.', 404);
        }
        if ($scene['revision'] !== $validated['revision']) {
            $this->throwConflict($scene['revision']);
        }
        if (!$scene['is_visible']) {
            throw new SceneException(
                'scene_not_visible',
                'A hidden scene cannot be activated for players.',
                422
            );
        }
        $now = date('Y-m-d H:i:s');
        $userId = (int) ($auth['user_id'] ?? 0) ?: null;
        $stateTable = $this->db->prefixTable('campaign_scene_state');
        $sceneTable = $this->db->prefixTable('scenes');
        $written = $this->db->query(
            'INSERT INTO ' . $stateTable . ' '
            . '(campaign_id, active_scene_id, revision, updated_by, created_at, updated_at) '
            . 'SELECT s.campaign_id, s.id, 1, ?, ?, ? FROM ' . $sceneTable . ' s '
            . 'WHERE s.id = ? AND s.campaign_id = ? AND s.revision = ? '
            . 'AND s.is_visible = 1 AND s.deleted_at IS NULL '
            . 'ON DUPLICATE KEY UPDATE '
            . 'active_scene_id = VALUES(active_scene_id), revision = '
            . $stateTable . '.revision + 1, '
            . 'updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)',
            [$userId, $now, $now, $sceneId, $campaignId, $validated['revision']]
        );
        if ($written === false) {
            throw new SceneException('scene_write_failed', 'Active scene could not be saved.', 500);
        }
        if ($this->db->affectedRows() < 1) {
            $this->throwActivationFailure($campaignId, $sceneId, $validated['revision']);
        }
        $state = $this->state->find($campaignId);
        if (!$state || $state['active_scene_id'] !== $sceneId) {
            throw new SceneException('scene_write_failed', 'Active scene could not be verified.', 500);
        }
        return [
            'scene' => $scene,
            'activeSceneId' => $state['active_scene_id'],
            'capabilities' => $capabilities,
        ];
    }

    private function authorize(int $campaignId, array $auth, bool $manage): array
    {
        $access = $this->access->forCampaign($auth, $campaignId);
        if (!$access['exists']) {
            throw new SceneException('campaign_not_found', 'Campaign was not found.', 404);
        }
        if (!$access['allowed'] || ($manage && !$access['capabilities']['canManage'])) {
            throw new SceneException('forbidden', 'You do not have access to this campaign operation.', 403);
        }
        return $access['capabilities'];
    }

    private function findScene(int $campaignId, int $sceneId): ?array
    {
        return $this->scenes->where('campaign_id', $campaignId)->where('id', $sceneId)->first();
    }

    private function assertSceneManager(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $capabilities
    ): void {
        if (!$this->findScene($campaignId, $sceneId)) {
            throw new SceneException('scene_not_found', 'Scene was not found.', 404);
        }
        if (!$this->resourceAccess->canManage($auth, $campaignId, $sceneId, $capabilities)) {
            throw new SceneException('forbidden', 'You cannot modify this scene.', 403);
        }
    }

    private function assertValid(array $validated): void
    {
        if (!$validated['valid']) {
            throw new SceneException('validation_failed', 'Scene payload is invalid.', 422, $validated['errors']);
        }
    }

    private function throwMissingOrConflict(int $campaignId, int $sceneId): void
    {
        $scene = $this->findScene($campaignId, $sceneId);
        if (!$scene) {
            throw new SceneException('scene_not_found', 'Scene was not found.', 404);
        }
        $this->throwConflict((int) $scene['revision']);
    }

    private function throwActivationFailure(int $campaignId, int $sceneId, int $revision): void
    {
        $scene = $this->findScene($campaignId, $sceneId);
        if (!$scene) {
            throw new SceneException('scene_not_found', 'Scene was not found.', 404);
        }
        if ($scene['revision'] !== $revision) {
            $this->throwConflict($scene['revision']);
        }
        if (!$scene['is_visible']) {
            throw new SceneException(
                'scene_not_visible',
                'A hidden scene cannot be activated for players.',
                422
            );
        }
        throw new SceneException('scene_write_failed', 'Active scene could not be saved.', 500);
    }

    private function throwConflict(int $revision): void
    {
        throw new SceneException(
            'revision_conflict',
            'Scene changed since it was loaded.',
            409,
            ['currentRevision' => $revision]
        );
    }

    private function currentDarkness(array $scene, \DateTimeImmutable $now): float
    {
        $fallback = max(0.0, min(1.0, (float) ($scene['darkness_level'] ?? 0)));
        $started = $scene['darkness_transition_started_at'] ?? null;
        $duration = (int) ($scene['darkness_transition_duration'] ?? 0);
        if (!$started || $duration < 1) return $fallback;
        try {
            $start = new \DateTimeImmutable((string) $started, new \DateTimeZone('UTC'));
        } catch (\Throwable $exception) {
            return $fallback;
        }
        $elapsed = max(0, ((float) $now->format('U.u') - (float) $start->format('U.u')) * 1000);
        $progress = min(1.0, $elapsed / $duration);
        $from = (float) ($scene['darkness_transition_from'] ?? $fallback);
        $to = (float) ($scene['darkness_transition_to'] ?? $fallback);
        return max(0.0, min(1.0, $from + ($to - $from) * $progress));
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new SceneException('scene_write_failed', 'Scene state could not be saved.', 500);
        }
        $this->db->transCommit();
    }
}
