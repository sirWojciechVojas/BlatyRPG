<?php

namespace App\Services\Token;

use App\Models\SceneTokenSyncLinkModel;
use CodeIgniter\Database\BaseConnection;

/** Authoritative one-way synchronization for tokens representing one character. */
final class TokenSyncService
{
    private const FIELD_MAP = [
        'name' => 'name',
        'image_url' => 'imageUrl',
        'disposition' => 'disposition',
        'hidden' => 'hidden',
        'locked' => 'locked',
        'rotation_handle_enabled' => 'rotationHandleEnabled',
        'facing_handle_enabled' => 'facingHandleEnabled',
        'rotation_follows_facing' => 'rotationFollowsFacing',
        'show_info_unselected' => 'showInfoUnselected',
        'resource_bar_position' => 'resourceBarPosition',
        'movement_range' => 'movementRange',
        'movement_spent' => 'movementSpent',
        'movement_reset_mode' => 'movementResetMode',
        'visible_to_json' => 'visibleTo',
        'controlled_by_json' => 'controlledBy',
        'editable_by_json' => 'editableBy',
        'observer_by_json' => 'observerBy',
        'statuses_json' => 'statuses',
        'bars_json' => 'resources',
        'vision_json' => 'vision',
    ];

    private $db;
    private $links;
    private $access;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneTokenSyncLinkModel $links = null,
        ?TokenAccessService $access = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->links = $links ?: new SceneTokenSyncLinkModel($this->db);
        $this->access = $access ?: new TokenAccessService($this->db);
    }

    public static function apiFields(): array
    {
        return array_values(self::FIELD_MAP);
    }

    public static function databaseFields(): array
    {
        return array_keys(self::FIELD_MAP);
    }

    public function catalog(int $campaignId, array $auth): array
    {
        $this->assertGameMaster($campaignId, $auth);
        $scenes = $this->db->table('scenes')
            ->select('id, name, sort_order, is_visible')
            ->where('campaign_id', $campaignId)->where('deleted_at', null)
            ->orderBy('sort_order', 'ASC')->orderBy('id', 'ASC')
            ->get()->getResultArray();
        $tokens = $this->db->table('scene_tokens tokens')
            ->select('tokens.id, tokens.scene_id, tokens.character_id, tokens.name, '
                . 'tokens.image_url, tokens.revision, scenes.name AS scene_name')
            ->join('scenes', 'scenes.id = tokens.scene_id', 'inner')
            ->where('tokens.campaign_id', $campaignId)
            ->where('tokens.character_id IS NOT NULL', null, false)
            ->where('tokens.deleted_at', null)->where('scenes.deleted_at', null)
            ->orderBy('scenes.sort_order', 'ASC')->orderBy('tokens.name', 'ASC')
            ->orderBy('tokens.id', 'ASC')->get()->getResultArray();
        $links = $this->links->where('campaign_id', $campaignId)
            ->orderBy('source_token_id', 'ASC')->orderBy('target_token_id', 'ASC')
            ->findAll();
        return [
            'scenes' => array_map(static fn (array $scene): array => [
                'id' => (int) $scene['id'],
                'name' => (string) $scene['name'],
                'sortOrder' => (int) ($scene['sort_order'] ?? 0),
                'isVisible' => !empty($scene['is_visible']),
            ], $scenes),
            'tokens' => array_map(static fn (array $token): array => [
                'id' => (int) $token['id'],
                'sceneId' => (int) $token['scene_id'],
                'sceneName' => (string) $token['scene_name'],
                'characterId' => (int) $token['character_id'],
                'name' => (string) $token['name'],
                'imageUrl' => (string) ($token['image_url'] ?? ''),
                'revision' => (int) $token['revision'],
            ], $tokens),
            'links' => array_map([$this, 'presentLink'], $links),
            'capabilities' => ['canManage' => true],
            'syncFields' => self::apiFields(),
        ];
    }

    public function preview(int $campaignId, array $auth, array $payload): array
    {
        $this->assertGameMaster($campaignId, $auth);
        $this->assertExactKeys($payload, ['sourceTokenId', 'targetTokenIds']);
        $sourceId = $this->positiveId($payload['sourceTokenId'] ?? null, 'sourceTokenId');
        $targetIds = $this->targetIds($payload['targetTokenIds'] ?? null);
        $source = $this->token($campaignId, $sourceId);
        $targets = [];
        foreach ($targetIds as $targetId) {
            $target = $this->token($campaignId, $targetId);
            $this->assertCompatible($source, $target);
            $targets[] = $this->previewTarget($source, $target);
        }
        return [
            'sourceTokenId' => $sourceId,
            'sourceRevision' => (int) $source['revision'],
            'targets' => $targets,
            'syncFields' => self::apiFields(),
        ];
    }

    public function transfer(int $campaignId, array $auth, array $payload): array
    {
        $this->assertGameMaster($campaignId, $auth);
        return $this->applyMany($campaignId, $auth, $payload, false);
    }

    public function createLinks(int $campaignId, array $auth, array $payload): array
    {
        $this->assertGameMaster($campaignId, $auth);
        return $this->applyMany($campaignId, $auth, $payload, true);
    }

    public function updateLink(int $campaignId, int $linkId, array $auth, array $payload): array
    {
        $this->assertGameMaster($campaignId, $auth);
        if (array_keys($payload) !== ['enabled'] || !is_bool($payload['enabled'])) {
            throw new TokenSyncException('validation_failed', 'Link state is invalid.', 422, [
                'enabled' => 'A boolean is required.',
            ]);
        }
        $link = $this->link($campaignId, $linkId);
        $this->links->update($linkId, ['enabled' => $payload['enabled'] ? 1 : 0]);
        return ['link' => $this->presentLink($this->link($campaignId, $linkId))];
    }

    public function applyLink(int $campaignId, int $linkId, array $auth): array
    {
        $this->assertGameMaster($campaignId, $auth);
        $link = $this->link($campaignId, $linkId);
        $this->db->transBegin();
        try {
            $locked = $this->lockTokens($campaignId, [
                (int) $link['source_token_id'],
                (int) $link['target_token_id'],
            ]);
            $source = $locked[(int) $link['source_token_id']];
            $target = $locked[(int) $link['target_token_id']];
            $this->assertCompatible($source, $target);
            $updated = $this->writeTarget($campaignId, $source, $target, self::databaseFields());
            $this->links->update($linkId, [
                'diverged_fields_json' => [],
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);
            $this->commitOrFail();
            return [
                'link' => $this->presentLink($this->link($campaignId, $linkId)),
                'synchronizedTokens' => [$this->tokenResult(
                    $campaignId, $updated, $auth, self::databaseFields()
                )],
            ];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function deleteLink(int $campaignId, int $linkId, array $auth): array
    {
        $this->assertGameMaster($campaignId, $auth);
        $this->link($campaignId, $linkId);
        $this->links->delete($linkId, true);
        return ['deleted' => true, 'id' => $linkId];
    }

    /** Called inside the token write transaction. */
    public function afterTokenUpdate(
        int $campaignId,
        int $tokenId,
        array $databaseFields,
        array $auth
    ): array {
        $fields = array_values(array_intersect(array_keys($databaseFields), self::databaseFields()));
        if (!$fields || !$this->db->tableExists('scene_token_sync_links')) return [];

        $incoming = $this->links->where('campaign_id', $campaignId)
            ->where('target_token_id', $tokenId)->first();
        if ($incoming) {
            $diverged = array_values(array_unique(array_merge(
                (array) ($incoming['diverged_fields_json'] ?? []),
                array_map(static fn (string $field): string => self::FIELD_MAP[$field], $fields)
            )));
            $this->links->update((int) $incoming['id'], ['diverged_fields_json' => $diverged]);
            return [];
        }

        $outgoing = $this->links->where('campaign_id', $campaignId)
            ->where('source_token_id', $tokenId)->where('enabled', 1)
            ->orderBy('target_token_id', 'ASC')->findAll();
        if (!$outgoing) return [];
        $source = $this->token($campaignId, $tokenId);
        $results = [];
        foreach ($outgoing as $link) {
            $target = $this->token($campaignId, (int) $link['target_token_id']);
            $updated = $this->writeTarget($campaignId, $source, $target, $fields);
            $applied = array_map(static fn (string $field): string => self::FIELD_MAP[$field], $fields);
            $diverged = array_values(array_diff(
                (array) ($link['diverged_fields_json'] ?? []),
                $applied
            ));
            $this->links->update((int) $link['id'], [
                'diverged_fields_json' => $diverged,
                'last_synced_at' => date('Y-m-d H:i:s'),
            ]);
            $results[] = $this->tokenResult($campaignId, $updated, $auth, $fields);
        }
        return $results;
    }

    public function deleteTokenLinks(int $campaignId, int $tokenId): void
    {
        if (!$this->db->tableExists('scene_token_sync_links')) return;
        $this->db->table('scene_token_sync_links')->where('campaign_id', $campaignId)
            ->groupStart()->where('source_token_id', $tokenId)
            ->orWhere('target_token_id', $tokenId)->groupEnd()->delete();
    }

    private function applyMany(int $campaignId, array $auth, array $payload, bool $createLinks): array
    {
        $this->assertExactKeys($payload, ['sourceTokenId', 'sourceRevision', 'targets']);
        $sourceId = $this->positiveId($payload['sourceTokenId'] ?? null, 'sourceTokenId');
        $sourceRevision = $this->positiveId($payload['sourceRevision'] ?? null, 'sourceRevision');
        $targets = $this->targetRevisions($payload['targets'] ?? null);
        $this->db->transBegin();
        try {
            $locked = $this->lockTokens(
                $campaignId,
                array_merge([$sourceId], array_keys($targets))
            );
            $source = $locked[$sourceId];
            if ((int) $source['revision'] !== $sourceRevision) $this->revisionConflict('sourceTokenId');
            if ($createLinks) $this->assertSourceAvailable($campaignId, $sourceId);
            $updated = [];
            $created = [];
            foreach ($targets as $targetId => $revision) {
                $target = $locked[$targetId];
                if ((int) $target['revision'] !== $revision) $this->revisionConflict('targets');
                $this->assertCompatible($source, $target);
                if ($createLinks) $this->assertTargetAvailable($campaignId, $sourceId, $targetId);
                $written = $this->writeTarget($campaignId, $source, $target, self::databaseFields());
                $updated[] = $this->tokenResult(
                    $campaignId, $written, $auth, self::databaseFields()
                );
                if ($createLinks) {
                    $now = date('Y-m-d H:i:s');
                    $ok = $this->db->table('scene_token_sync_links')->insert([
                        'campaign_id' => $campaignId,
                        'source_scene_id' => (int) $source['scene_id'],
                        'source_token_id' => $sourceId,
                        'target_scene_id' => (int) $target['scene_id'],
                        'target_token_id' => $targetId,
                        'enabled' => 1,
                        'diverged_fields_json' => '[]',
                        'last_synced_at' => $now,
                        'created_by' => (int) $auth['user_id'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                    if (!$ok) throw new TokenSyncException(
                        'token_sync_write_failed', 'Token sync link could not be created.', 500
                    );
                    $created[] = (int) $this->db->insertID();
                }
            }
            $this->commitOrFail();
            return [
                'synchronizedTokens' => $updated,
                'links' => array_map(fn (int $id): array => $this->presentLink(
                    $this->link($campaignId, $id)
                ), $created),
            ];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function previewTarget(array $source, array $target): array
    {
        $sourcePresented = $this->presentManager($source);
        $targetPresented = $this->presentManager($target);
        $changes = [];
        foreach (self::apiFields() as $field) {
            if ($this->sameValue($sourcePresented[$field] ?? null, $targetPresented[$field] ?? null)) continue;
            $changes[] = [
                'field' => $field,
                'before' => $targetPresented[$field] ?? null,
                'after' => $sourcePresented[$field] ?? null,
            ];
        }
        return [
            'tokenId' => (int) $target['id'],
            'sceneId' => (int) $target['scene_id'],
            'name' => (string) $target['name'],
            'revision' => (int) $target['revision'],
            'changes' => $changes,
        ];
    }

    private function writeTarget(
        int $campaignId,
        array $source,
        array $target,
        array $fields
    ): array {
        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $source)) $data[$field] = $source[$field];
        }
        if (!$data) return $target;
        $data = TokenDatabasePayload::encode($data);
        $this->db->table('scene_tokens')->set($data)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->set('revision', 'revision + 1', false)
            ->where('campaign_id', $campaignId)->where('id', (int) $target['id'])
            ->where('revision', (int) $target['revision'])->where('deleted_at', null)->update();
        if ($this->db->affectedRows() !== 1) $this->revisionConflict('targets');
        return $this->token($campaignId, (int) $target['id']);
    }

    private function tokenResult(
        int $campaignId,
        array $row,
        array $auth,
        array $databaseFields
    ): array
    {
        $canManage = $this->isGameMaster($campaignId, $auth);
        $token = TokenPresenter::present(
            $row,
            $this->access->canControl($auth, $campaignId, $row, $canManage),
            $canManage,
            $this->access->canEdit($auth, $campaignId, $row, $canManage),
            $this->access->canObserve($auth, $campaignId, $row, $canManage)
        );
        $scene = $this->db->table('scenes')->select('is_visible')
            ->where('campaign_id', $campaignId)->where('id', (int) $row['scene_id'])
            ->where('deleted_at', null)->get()->getRowArray();
        return [
            'token' => $token,
            'publishToPlayers' => !empty($scene['is_visible']) && empty($token['hidden']),
            'changedFields' => array_values(array_map(
                static fn (string $field): string => self::FIELD_MAP[$field],
                array_values(array_intersect($databaseFields, self::databaseFields()))
            )),
        ];
    }

    private function presentManager(array $row): array
    {
        return TokenPresenter::present($row, true, true, true, true);
    }

    private function token(int $campaignId, int $tokenId): array
    {
        $row = $this->db->table('scene_tokens')->where('campaign_id', $campaignId)
            ->where('id', $tokenId)->where('deleted_at', null)->get()->getRowArray();
        if (!$row) throw new TokenSyncException('token_not_found', 'Token was not found.', 404);
        return $this->normalizeTokenRow($row);
    }

    private function lockedToken(int $campaignId, int $tokenId): array
    {
        $sql = 'SELECT * FROM scene_tokens WHERE campaign_id = ? AND id = ? AND deleted_at IS NULL';
        if (stripos((string) ($this->db->DBDriver ?? ''), 'SQLite') === false) $sql .= ' FOR UPDATE';
        $row = $this->db->query($sql, [$campaignId, $tokenId])->getRowArray();
        if (!$row) throw new TokenSyncException('token_not_found', 'Token was not found.', 404);
        return $this->normalizeTokenRow($row);
    }

    /** Locks every participating token in a stable order to avoid cross-request deadlocks. */
    private function lockTokens(int $campaignId, array $tokenIds): array
    {
        $ids = array_values(array_unique(array_map('intval', $tokenIds)));
        sort($ids, SORT_NUMERIC);
        $locked = [];
        foreach ($ids as $tokenId) {
            $locked[$tokenId] = $this->lockedToken($campaignId, $tokenId);
        }
        return $locked;
    }

    private function normalizeTokenRow(array $row): array
    {
        foreach (['id', 'campaign_id', 'scene_id', 'character_id', 'revision'] as $field) {
            if (isset($row[$field])) $row[$field] = (int) $row[$field];
        }
        foreach (['x', 'y', 'width', 'height', 'rotation', 'facing', 'elevation',
            'movement_range', 'movement_spent'] as $field) {
            if (isset($row[$field])) $row[$field] = (float) $row[$field];
        }
        foreach (['hidden', 'locked', 'rotation_handle_enabled', 'facing_handle_enabled',
            'rotation_follows_facing', 'show_info_unselected'] as $field) {
            if (isset($row[$field])) $row[$field] = !empty($row[$field]);
        }
        foreach (['visible_to_json', 'controlled_by_json', 'editable_by_json',
            'observer_by_json', 'bars_json', 'statuses_json', 'vision_json', 'light_json'] as $field) {
            if (!is_string($row[$field] ?? null)) continue;
            $decoded = json_decode($row[$field], true);
            $row[$field] = is_array($decoded) ? $decoded : [];
        }
        return $row;
    }

    private function assertCompatible(array $source, array $target): void
    {
        if ((int) $source['id'] === (int) $target['id']) {
            throw new TokenSyncException('validation_failed', 'A token cannot target itself.', 422);
        }
        if ((int) $source['scene_id'] === (int) $target['scene_id']) {
            throw new TokenSyncException('validation_failed', 'Tokens must be in different scenes.', 422, [
                'targets' => 'Choose tokens from another scene.',
            ]);
        }
        $characterId = (int) ($source['character_id'] ?? 0);
        if ($characterId < 1 || $characterId !== (int) ($target['character_id'] ?? 0)) {
            throw new TokenSyncException('validation_failed', 'Tokens must represent the same character.', 422, [
                'targets' => 'Choose tokens assigned to the same character.',
            ]);
        }
    }

    private function assertSourceAvailable(int $campaignId, int $sourceId): void
    {
        if ($this->links->where('campaign_id', $campaignId)
            ->where('target_token_id', $sourceId)->first()) {
            throw new TokenSyncException('token_sync_chain_forbidden', 'A listening token cannot be a source.', 422);
        }
    }

    private function assertTargetAvailable(int $campaignId, int $sourceId, int $targetId): void
    {
        if ($this->links->where('campaign_id', $campaignId)
            ->where('target_token_id', $targetId)->first()) {
            throw new TokenSyncException('token_sync_target_used', 'The target already listens to another token.', 409);
        }
        if ($this->links->where('campaign_id', $campaignId)
            ->where('source_token_id', $targetId)->first()) {
            throw new TokenSyncException('token_sync_chain_forbidden', 'A source token cannot become a listener.', 422);
        }
        if ($this->links->where('campaign_id', $campaignId)
            ->where('source_token_id', $sourceId)->where('target_token_id', $targetId)->first()) {
            throw new TokenSyncException('token_sync_duplicate', 'This link already exists.', 409);
        }
    }

    private function assertGameMaster(int $campaignId, array $auth): void
    {
        if (!$this->isGameMaster($campaignId, $auth)) {
            throw new TokenSyncException('forbidden', 'Only the campaign game master can manage token synchronization.', 403);
        }
    }

    private function isGameMaster(int $campaignId, array $auth): bool
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1) return false;
        if ($this->db->table('campaigns')->where('id', $campaignId)
            ->where('game_master_id', $userId)->countAllResults() === 1) {
            return true;
        }
        if (!$this->db->tableExists('campaign_members')) return false;
        return $this->db->table('campaign_members')->where('campaign_id', $campaignId)
            ->where('user_id', $userId)->where('role', 'gm')->where('is_active', 1)
            ->countAllResults() === 1;
    }

    private function link(int $campaignId, int $linkId): array
    {
        $link = $this->links->where('campaign_id', $campaignId)->where('id', $linkId)->first();
        if (!$link) throw new TokenSyncException('token_sync_link_not_found', 'Token sync link was not found.', 404);
        return $link;
    }

    private function presentLink(array $link): array
    {
        return [
            'id' => (int) $link['id'],
            'campaignId' => (int) $link['campaign_id'],
            'sourceSceneId' => (int) $link['source_scene_id'],
            'sourceTokenId' => (int) $link['source_token_id'],
            'targetSceneId' => (int) $link['target_scene_id'],
            'targetTokenId' => (int) $link['target_token_id'],
            'enabled' => !empty($link['enabled']),
            'divergedFields' => array_values((array) ($link['diverged_fields_json'] ?? [])),
            'lastSyncedAt' => $link['last_synced_at'] ?? null,
            'createdBy' => (int) $link['created_by'],
            'createdAt' => $link['created_at'] ?? null,
            'updatedAt' => $link['updated_at'] ?? null,
        ];
    }

    private function targetIds($value): array
    {
        if (!is_array($value) || !$value || count($value) > 250) {
            throw new TokenSyncException('validation_failed', 'Choose between 1 and 250 target tokens.', 422, [
                'targetTokenIds' => 'At least one target token is required.',
            ]);
        }
        $ids = array_map(fn ($id): int => $this->positiveId($id, 'targetTokenIds'), $value);
        if (count(array_unique($ids)) !== count($ids)) {
            throw new TokenSyncException('validation_failed', 'Target tokens must be unique.', 422);
        }
        sort($ids, SORT_NUMERIC);
        return $ids;
    }

    private function targetRevisions($value): array
    {
        if (!is_array($value) || !$value || count($value) > 250) {
            throw new TokenSyncException('validation_failed', 'Choose between 1 and 250 target tokens.', 422);
        }
        $result = [];
        foreach ($value as $target) {
            if (!is_array($target)) throw new TokenSyncException('validation_failed', 'Target token is invalid.', 422);
            $this->assertExactKeys($target, ['tokenId', 'revision']);
            $id = $this->positiveId($target['tokenId'] ?? null, 'targets');
            if (isset($result[$id])) throw new TokenSyncException('validation_failed', 'Target tokens must be unique.', 422);
            $result[$id] = $this->positiveId($target['revision'] ?? null, 'targets');
        }
        ksort($result, SORT_NUMERIC);
        return $result;
    }

    private function positiveId($value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new TokenSyncException('validation_failed', 'A valid id is required.', 422, [
            $field => 'A positive integer is required.',
        ]);
        return (int) $id;
    }

    private function assertExactKeys(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if (!$unexpected) return;
        throw new TokenSyncException(
            'validation_failed',
            'Token synchronization payload contains unsupported fields.',
            422,
            array_fill_keys($unexpected, 'This field is not accepted.')
        );
    }

    private function sameValue($left, $right): bool
    {
        $normalize = static function ($value): string {
            if (is_array($value)) {
                if (array_keys($value) !== range(0, count($value) - 1)) ksort($value);
                return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        };
        return $normalize($left) === $normalize($right);
    }

    private function revisionConflict(string $field): void
    {
        throw new TokenSyncException('revision_conflict', 'Token changed after the preview was created.', 409, [
            $field => 'Refresh the preview before applying changes.',
        ]);
    }

    private function commitOrFail(): void
    {
        if ($this->db->transStatus() === false) {
            throw new TokenSyncException('token_sync_write_failed', 'Token synchronization could not be saved.', 500);
        }
        $this->db->transCommit();
    }
}
