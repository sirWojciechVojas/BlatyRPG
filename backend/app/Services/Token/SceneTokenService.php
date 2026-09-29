<?php

namespace App\Services\Token;

use App\Services\Bestiary\CharacterBestiaryEncounterRecorder;
use App\Models\SceneTokenModel;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneService;
use App\Services\Wall\WallCollisionService;
use App\Services\Fog\SceneVisibilityService;
use CodeIgniter\Database\BaseConnection;

final class SceneTokenService
{
    private const OWNER_FIELDS = [
        'x', 'y', 'rotation', 'facing', 'elevation', 'statuses_json', 'bars_json',
        'resource_bar_position',
    ];
    private const MANAGER_FIELDS = [
        'character_id',
        'visible_to_json', 'controlled_by_json',
        'editable_by_json', 'observer_by_json', 'rotation_handle_enabled', 'facing_handle_enabled',
        'rotation_follows_facing',
        'movement_range', 'movement_spent', 'movement_reset_mode',
        'show_info_unselected', 'vision_json',
    ];
    private $db;
    private $tokens;
    private $scenes;
    private $sceneAccess;
    private $access;
    private $validator;
    private $collisions;
    private $grid;
    private $visibility;
    private $sync;
    private $bestiary;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneTokenModel $tokens = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $sceneAccess = null,
        ?TokenAccessService $access = null,
        ?TokenPayloadValidator $validator = null,
        ?WallCollisionService $collisions = null,
        ?TokenGridPositionService $grid = null,
        ?SceneVisibilityService $visibility = null,
        ?TokenSyncService $sync = null,
        ?CharacterBestiaryEncounterRecorder $bestiary = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->tokens = $tokens ?: new SceneTokenModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->sceneAccess = $sceneAccess ?: new SceneResourceAccessService();
        $this->access = $access ?: new TokenAccessService();
        $this->validator = $validator ?: new TokenPayloadValidator();
        $this->collisions = $collisions ?: new WallCollisionService($this->db);
        $this->grid = $grid ?: new TokenGridPositionService();
        $this->visibility = $visibility ?: new SceneVisibilityService($this->db);
        $this->sync = $sync ?: new TokenSyncService($this->db);
        $this->bestiary = $bestiary
            ?: new CharacterBestiaryEncounterRecorder($this->db);
    }

    public function list(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $query = $this->tokens->where('campaign_id', $campaignId)->where('scene_id', $sceneId);
        if (!$canManage) $query->where('hidden', 0);
        $rows = $query->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')->findAll();
        $visible = array_values(array_filter(
            $rows,
            fn (array $row): bool => $this->access->canView(
                $auth, $campaignId, $row, $canManage
            )
        ));
        if (!$canManage) {
            $visible = $this->visibility->filter($auth, $campaignId, $scene, $visible);
        }
        try {
            $this->bestiary->recordVisibleTokens(
                $campaignId,
                $sceneId,
                $auth,
                $visible,
                $canManage
            );
        } catch (\Throwable $exception) {
            log_message(
                'error',
                'Bestiary encounter recording failed: {message}',
                ['message' => $exception->getMessage()]
            );
        }
        return [
            'items' => array_map(
                fn (array $row): array => $this->presentRow(
                    $auth, $campaignId, $row, $canManage
                ),
                $visible
            ),
            'sceneRevision' => (int) $scene['revision'],
            'capabilities' => ['canCreate' => $canManage],
        ];
    }

    public function create(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $payload,
        array $serverData = []
    ): array
    {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->create($payload);
        $this->assertValid($validated);
        $this->access->assertCharacterInCampaign($campaignId, $validated['data']['character_id'] ?? null);
        $this->access->assertPermissionUsersInCampaign($campaignId, $validated['data']);
        $provenance = array_intersect_key($serverData, array_flip([
            'token_template_id', 'token_template_asset_id',
        ]));
        $data = $this->snapCreateData($scene, $validated['data']) + $provenance + [
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
        array $payload,
        array $movementRoute = [],
        bool $includeSynchronized = false
    ): array {
        [$scene, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $row = $this->find($campaignId, $sceneId, $tokenId);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        if (!$this->access->canView($auth, $campaignId, $row, $canManage)) {
            throw new TokenException('token_not_found', 'Token was not found.', 404);
        }
        $canControl = $this->access->canControl($auth, $campaignId, $row, $canManage);
        $canEdit = $this->access->canEdit($auth, $campaignId, $row, $canManage);
        if (!$canControl && !$canEdit) {
            throw new TokenException('forbidden', 'You cannot modify this token.', 403);
        }
        if (!$canManage && !empty($row['locked'])) {
            throw new TokenException('token_locked', 'This token is locked by the game master.', 403);
        }
        $validated = $this->validator->update($payload);
        $this->assertValid($validated);
        $validated['data'] = $this->snapUpdateData($scene, $row, $validated['data']);
        if (!$canManage && array_intersect(array_keys($validated['data']), self::MANAGER_FIELDS)) {
            throw new TokenException('forbidden', 'Only a game master can configure protected token fields.', 403);
        }
        if (!$canManage && !$canEdit
            && array_diff(array_keys($validated['data']), self::OWNER_FIELDS)) {
            throw new TokenException('forbidden', 'Only a scene manager can configure this token.', 403);
        }
        if (array_key_exists('character_id', $validated['data'])) {
            $this->access->assertCharacterInCampaign($campaignId, $validated['data']['character_id']);
        }
        if ($canManage) {
            $this->access->assertPermissionUsersInCampaign($campaignId, $validated['data']);
        }
        $resources = new TokenResourceMovementService($this->db);
        $this->db->transBegin();
        try {
            $validated['data'] = $resources->prepare(
                $campaignId, $row, $validated['data'], $canManage
            );
            $movement = (new TokenMovementService($this->collisions, $this->grid))->apply(
                $campaignId, $sceneId, $scene, $row,
                $validated['data'], $movementRoute, $canManage
            );
            $validated['data'] = $resources->finish(
                $campaignId, $row, array_merge($validated['data'], $movement)
            );
            $syncChanges = $this->changedValues($row, $validated['data']);
            $this->writeRevision(
                $campaignId,
                $sceneId,
                $tokenId,
                $validated['revision'],
                $validated['data']
            );
            if (array_key_exists('character_id', $syncChanges)) {
                $this->sync->deleteTokenLinks($campaignId, $tokenId);
            }
            $synchronized = $this->sync->afterTokenUpdate(
                $campaignId,
                $tokenId,
                $syncChanges,
                $auth
            );
            if ($this->db->transStatus() === false) {
                throw new TokenException(
                    'token_write_failed', 'Token changes could not be saved.', 500
                );
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        $token = $this->present($campaignId, $sceneId, $tokenId, $auth);
        $result = [
            'token' => $token,
            'visibility' => [
                'publishToPlayers' => !empty($scene['is_visible']) && empty($token['hidden']),
            ],
        ];
        if ($includeSynchronized && $synchronized) {
            $result['synchronizedTokens'] = $synchronized;
        }
        return $result;
    }

    public function delete(int $campaignId, int $sceneId, int $tokenId, array $auth, array $payload): array
    {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $this->assertManager($auth, $campaignId, $sceneId, $capabilities);
        $validated = $this->validator->deletion($payload);
        $this->assertValid($validated);
        $this->find($campaignId, $sceneId, $tokenId);
        $this->db->transBegin();
        try {
            $this->writeRevision($campaignId, $sceneId, $tokenId, $validated['revision'], [
                'deleted_at' => date('Y-m-d H:i:s'),
            ]);
            $this->sync->deleteTokenLinks($campaignId, $tokenId);
            if ($this->db->transStatus() === false) {
                throw new TokenException('token_write_failed', 'Token could not be deleted.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
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

    private function changedValues(array $current, array $next): array
    {
        return array_filter(
            $next,
            static function ($value, string $field) use ($current): bool {
                if (!array_key_exists($field, $current)) return true;
                $normalize = static function ($candidate): string {
                    if (is_array($candidate)) {
                        $copy = $candidate;
                        if ($copy && array_keys($copy) !== range(0, count($copy) - 1)) {
                            ksort($copy);
                        }
                        return (string) json_encode($copy, JSON_UNESCAPED_UNICODE);
                    }
                    if (is_bool($candidate)) return $candidate ? '1' : '0';
                    if (is_numeric($candidate)) return (string) (float) $candidate;
                    return (string) $candidate;
                };
                return $normalize($current[$field]) !== $normalize($value);
            },
            ARRAY_FILTER_USE_BOTH
        );
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
        return $this->presentRow($auth, $campaignId, $row, $canManage);
    }

    private function presentRow(array $auth, int $campaignId, array $row, bool $canManage): array
    {
        return TokenPresenter::present(
            $row,
            $this->access->canControl($auth, $campaignId, $row, $canManage),
            $canManage,
            $this->access->canEdit($auth, $campaignId, $row, $canManage),
            $this->access->canObserve($auth, $campaignId, $row, $canManage)
        );
    }

    private function assertValid(array $validated): void
    {
        if (empty($validated['valid'])) {
            throw new TokenException('validation_failed', 'Token payload is invalid.', 422, $validated['errors']);
        }
    }

    private function snapCreateData(array $scene, array $data): array
    {
        $defaultSize = max(1.0, (float) ($scene['grid_size'] ?? 100));
        $data['width'] = (float) ($data['width'] ?? $defaultSize);
        $data['height'] = (float) ($data['height'] ?? $defaultSize);
        $position = $this->grid->snap($scene, $data, $data['width'], $data['height']);
        return array_merge($data, $position);
    }
    private function snapUpdateData(array $scene, array $token, array $data): array
    {
        $positionFields = ['x', 'y', 'width', 'height'];
        if (!array_intersect($positionFields, array_keys($data))) return $data;
        $position = $this->grid->snap(
            $scene,
            [
                'x' => $data['x'] ?? $token['x'],
                'y' => $data['y'] ?? $token['y'],
            ],
            (float) ($data['width'] ?? $token['width']),
            (float) ($data['height'] ?? $token['height'])
        );
        return array_merge($data, $position);
    }

    private function writeRevision(int $campaignId, int $sceneId, int $tokenId, int $revision, array $data): void
    {
        $data = TokenDatabasePayload::encode($data);
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
