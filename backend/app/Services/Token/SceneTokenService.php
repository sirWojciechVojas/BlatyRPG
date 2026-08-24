<?php

namespace App\Services\Token;

use App\Models\SceneTokenModel;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneService;
use App\Services\Wall\WallCollisionService;
use CodeIgniter\Database\BaseConnection;

final class SceneTokenService
{
    private const OWNER_FIELDS = ['x', 'y', 'rotation', 'elevation'];
    private $db;
    private $tokens;
    private $scenes;
    private $sceneAccess;
    private $access;
    private $validator;
    private $collisions;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneTokenModel $tokens = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $sceneAccess = null,
        ?TokenAccessService $access = null,
        ?TokenPayloadValidator $validator = null,
        ?WallCollisionService $collisions = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->tokens = $tokens ?: new SceneTokenModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->sceneAccess = $sceneAccess ?: new SceneResourceAccessService();
        $this->access = $access ?: new TokenAccessService();
        $this->validator = $validator ?: new TokenPayloadValidator();
        $this->collisions = $collisions ?: new WallCollisionService($this->db);
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $query = $this->tokens->where('campaign_id', $campaignId)->where('scene_id', $sceneId);
        if (!$canManage) $query->where('hidden', 0);
        $rows = $query->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        return [
            'items' => array_map(function (array $row) use ($auth, $campaignId, $canManage): array {
                return TokenPresenter::present(
                    $row,
                    $this->access->canControl($auth, $campaignId, $row, $canManage),
                    $canManage
                );
            }, $rows),
            'sceneRevision' => (int) $scene['revision'],
            'capabilities' => ['canCreate' => $canManage],
        ];
    }

    public function create(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->create($payload);
        $this->assertValid($validated);
        $this->access->assertCharacterInCampaign($campaignId, $validated['data']['character_id'] ?? null);
        $data = $validated['data'] + [
            'campaign_id' => $campaignId,
            'scene_id' => $sceneId,
            'revision' => 1,
        ];
        if (!$this->tokens->insert($data)) {
            throw new TokenException('validation_failed', 'Token could not be created.', 422, $this->tokens->errors());
        }
        return ['token' => $this->present($campaignId, $sceneId, (int) $this->tokens->getInsertID(), $auth)];
    }

    public function update(
        int $campaignId,
        int $sceneId,
        int $tokenId,
        array $auth,
        array $payload
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $row = $this->find($campaignId, $sceneId, $tokenId);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $canControl = $this->access->canControl($auth, $campaignId, $row, $canManage);
        if (!$canControl) throw new TokenException('forbidden', 'You cannot control this token.', 403);
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        if (!$canManage && array_diff(array_keys($validated['data']), self::OWNER_FIELDS)) {
            throw new TokenException('forbidden', 'Only a scene manager can configure this token.', 403);
        }
        if (array_key_exists('character_id', $validated['data'])) {
            $this->access->assertCharacterInCampaign($campaignId, $validated['data']['character_id']);
        }
        $this->assertMovementAllowed($campaignId, $sceneId, $row, $validated['data']);
        $this->writeRevision($campaignId, $sceneId, $tokenId, $validated['revision'], $validated['data']);
        $token = $this->present($campaignId, $sceneId, $tokenId, $auth);
        return [
            'token' => $token,
            'visibility' => [
                'publishToPlayers' => !empty($scene['is_visible']) && empty($token['hidden']),
            ],
        ];
    }

    public function delete(int $campaignId, int $sceneId, int $tokenId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($campaignId, $sceneId, $tokenId);
        $this->writeRevision($campaignId, $sceneId, $tokenId, $validated['revision'], [
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
        return ['deleted' => true, 'id' => $tokenId];
    }

    private function sceneContext(int $campaignId, int $sceneId, array $auth): array
    {
        try {
            $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new TokenException(
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
        return $this->sceneAccess->canManage($auth, $campaignId, $sceneId, $caps);
    }

    private function assertManager(array $auth, int $campaignId, int $sceneId, array $caps): void
    {
        if (!$this->canManage($auth, $campaignId, $sceneId, $caps)) {
            throw new TokenException('forbidden', 'You cannot manage tokens on this scene.', 403);
        }
    }

    private function find(int $campaignId, int $sceneId, int $tokenId): array
    {
        $row = $this->tokens->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $tokenId)->first();
        if (!$row) throw new TokenException('token_not_found', 'Token was not found.', 404);
        return $row;
    }

    private function present(int $campaignId, int $sceneId, int $tokenId, array $auth): array
    {
        $row = $this->find($campaignId, $sceneId, $tokenId);
        $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $result['capabilities']);
        return TokenPresenter::present(
            $row,
            $this->access->canControl($auth, $campaignId, $row, $canManage),
            $canManage
        );
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new TokenException('validation_failed', 'Token payload is invalid.', 422, $validated['errors']);
        }
    }

    private function assertMovementAllowed(
        int $campaignId,
        int $sceneId,
        array $token,
        array $changes
    ): void {
        if (!array_key_exists('x', $changes) && !array_key_exists('y', $changes)) return;
        $width = (float) ($changes['width'] ?? $token['width']);
        $height = (float) ($changes['height'] ?? $token['height']);
        $start = [
            'x' => (float) $token['x'] + (float) $token['width'] / 2,
            'y' => (float) $token['y'] + (float) $token['height'] / 2,
        ];
        $end = [
            'x' => (float) ($changes['x'] ?? $token['x']) + $width / 2,
            'y' => (float) ($changes['y'] ?? $token['y']) + $height / 2,
        ];
        if ($this->collisions->blocksSceneMovement($campaignId, $sceneId, $start, $end)) {
            throw new TokenException(
                'movement_blocked',
                'Token movement is blocked by a wall or closed door.',
                422
            );
        }
    }

    private function writeRevision(int $campaignId, int $sceneId, int $tokenId, int $revision, array $data): void
    {
        $this->db->table('scene_tokens')->set($data)->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $tokenId)->where('revision', $revision)
            ->where('deleted_at', null)->update();
        if ($this->db->affectedRows() === 1) return;
        $row = $this->find($campaignId, $sceneId, $tokenId);
        throw new TokenException('revision_conflict', 'Token changed since it was loaded.', 409, [
            'currentRevision' => (int) $row['revision'],
        ]);
    }
}
