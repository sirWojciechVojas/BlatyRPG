<?php

namespace App\Services\Combat;

use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use App\Services\Token\SceneTokenService;
use App\Services\Token\TokenMovementResetService;
use CodeIgniter\Database\BaseConnection;

final class SceneCombatService
{
    private $db;
    private $scenes;
    private $sceneAccess;
    private $tokens;
    private $movement;
    private $turns;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $sceneAccess = null,
        ?SceneTokenService $tokens = null,
        ?TokenMovementResetService $movement = null,
        ?CombatTurnCoordinator $turns = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->sceneAccess = $sceneAccess ?: new SceneResourceAccessService();
        $this->tokens = $tokens ?: new SceneTokenService($this->db);
        $this->movement = $movement ?: new TokenMovementResetService($this->db);
        $this->turns = $turns ?: new CombatTurnCoordinator($this->db, $this->movement);
    }

    public function get(int $campaignId, int $sceneId, array $auth): array
    {
        $canManage = $this->context($campaignId, $sceneId, $auth);
        return $this->snapshot($campaignId, $sceneId, $auth, $canManage);
    }

    public function command(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        $canManage = $this->context($campaignId, $sceneId, $auth);
        if (!$canManage) throw new CombatException('forbidden', 'Only the game master can manage combat.', 403);
        $action = (string) ($payload['action'] ?? '');
        CombatCommandValidator::assertFields($action, $payload);
        $movementChanged = false;
        if ($action === 'start') $movementChanged = $this->start($campaignId, $sceneId, $auth, $payload);
        elseif ($action === 'end') $this->end($campaignId, $sceneId, $payload);
        elseif ($action === 'next') $movementChanged = $this->advance($campaignId, $sceneId, $payload, 1);
        elseif ($action === 'previous') $this->advance($campaignId, $sceneId, $payload, -1);
        elseif ($action === 'toggle') $this->toggleCombatant($campaignId, $sceneId, $payload);
        elseif ($action === 'initiative') $this->initiative($campaignId, $sceneId, $payload);
        elseif ($action === 'resetMovement') $movementChanged = $this->resetMovement($campaignId, $sceneId, $payload);
        elseif ($action === 'setMovement') $movementChanged = $this->setMovement($campaignId, $sceneId, $auth, $payload);
        else throw new CombatException('combat_action_invalid', 'Combat action is invalid.', 422);
        return $this->snapshot($campaignId, $sceneId, $auth, true) + [
            'movementChanged' => $movementChanged,
            'action' => $action,
            'publishToPlayers' => $this->sceneIsVisible($campaignId, $sceneId),
        ];
    }

    private function start(int $campaignId, int $sceneId, array $auth, array $payload): bool
    {
        $tokenIds = $this->tokenIds($payload['tokenIds'] ?? []);
        if (!$tokenIds) {
            $rows = $this->db->table('scene_tokens')->select('id')
                ->where('campaign_id', $campaignId)->where('scene_id', $sceneId)
                ->where('deleted_at', null)->orderBy('sort_order', 'ASC')->get()->getResultArray();
            $tokenIds = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        }
        if (!$tokenIds) throw new CombatException('combat_empty', 'The scene has no tokens.', 422);
        $this->assertSceneTokens($campaignId, $sceneId, $tokenIds);
        $combat = $this->combat($campaignId, $sceneId);
        if ($combat && (int) ($payload['revision'] ?? 0) !== (int) $combat['revision']) {
            throw new CombatException('revision_conflict', 'Combat changed since it was loaded.', 409, [
                'currentRevision' => (int) $combat['revision'],
            ]);
        }
        $now = date('Y-m-d H:i:s');
        $this->db->transBegin();
        $movementChanged = false;
        try {
            if ($combat) {
                $this->db->table('scene_combats')->set([
                    'active' => 1, 'round' => 1, 'turn_index' => 0, 'updated_at' => $now,
                ])->set('revision', 'revision + 1', false)->where('id', $combat['id'])->update();
                $combatId = (int) $combat['id'];
                $this->db->table('scene_combatants')->where('combat_id', $combatId)->delete();
            } else {
                $this->db->table('scene_combats')->insert([
                    'campaign_id' => $campaignId, 'scene_id' => $sceneId, 'active' => 1,
                    'round' => 1, 'turn_index' => 0, 'revision' => 1,
                    'created_by_user_id' => (int) ($auth['user_id'] ?? 0) ?: null,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $combatId = (int) $this->db->insertID();
            }
            $rows = [];
            foreach ($tokenIds as $index => $tokenId) $rows[] = [
                'combat_id' => $combatId, 'token_id' => $tokenId,
                'initiative' => 0, 'sort_order' => $index,
                'created_at' => $now, 'updated_at' => $now,
            ];
            $this->db->table('scene_combatants')->insertBatch($rows);
            $movementChanged = $this->movement->resetForAdvance(
                $campaignId, 'turn', [$tokenIds[0]]
            ) > 0;
            $this->finishTransaction();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $movementChanged;
    }

    private function end(int $campaignId, int $sceneId, array $payload): void
    {
        $combat = $this->requiredCombat($campaignId, $sceneId, $payload);
        $this->writeCombat($combat, ['active' => 0]);
    }

    private function advance(int $campaignId, int $sceneId, array $payload, int $direction): bool
    {
        $combat = $this->requiredCombat($campaignId, $sceneId, $payload, true);
        $rows = $this->combatants((int) $combat['id']);
        return $this->turns->advance(
            $campaignId, $combat, $rows, $direction,
            fn (array $state, array $changes) => $this->writeCombat($state, $changes)
        );
    }

    private function toggleCombatant(int $campaignId, int $sceneId, array $payload): void
    {
        $combat = $this->requiredCombat($campaignId, $sceneId, $payload);
        $tokenId = $this->positiveId($payload['tokenId'] ?? null, 'token_id_invalid');
        $this->assertSceneTokens($campaignId, $sceneId, [$tokenId]);
        $existing = $this->db->table('scene_combatants')->where([
            'combat_id' => $combat['id'], 'token_id' => $tokenId,
        ])->get()->getRowArray();
        if ($existing) $this->db->table('scene_combatants')->where('id', $existing['id'])->delete();
        else $this->db->table('scene_combatants')->insert([
            'combat_id' => $combat['id'], 'token_id' => $tokenId,
            'initiative' => 0, 'sort_order' => count($this->combatants((int) $combat['id'])),
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $count = count($this->combatants((int) $combat['id']));
        $this->writeCombat($combat, ['turn_index' => max(0, min((int) $combat['turn_index'], $count - 1))]);
    }

    private function initiative(int $campaignId, int $sceneId, array $payload): void
    {
        $combat = $this->requiredCombat($campaignId, $sceneId, $payload);
        $tokenId = $this->positiveId($payload['tokenId'] ?? null, 'token_id_invalid');
        $value = filter_var($payload['initiative'] ?? null, FILTER_VALIDATE_FLOAT);
        if ($value === false || abs((float) $value) > 100000) {
            throw new CombatException('initiative_invalid', 'Initiative is invalid.', 422);
        }
        $before = $this->combatants((int) $combat['id']);
        $current = $before[min(count($before) - 1, (int) $combat['turn_index'])] ?? null;
        $row = $this->db->table('scene_combatants')->where([
            'combat_id' => $combat['id'], 'token_id' => $tokenId,
        ])->get()->getRowArray();
        if (!$row) throw new CombatException('combatant_not_found', 'Combatant was not found.', 404);
        $this->db->table('scene_combatants')->set([
            'initiative' => round((float) $value, 3), 'updated_at' => date('Y-m-d H:i:s'),
        ])->where(['combat_id' => $combat['id'], 'token_id' => $tokenId])->update();
        $turnIndex = (int) $combat['turn_index'];
        if ($current) {
            foreach ($this->combatants((int) $combat['id']) as $index => $candidate) {
                if ((int) $candidate['token_id'] === (int) $current['token_id']) $turnIndex = $index;
            }
        }
        $this->writeCombat($combat, ['turn_index' => $turnIndex]);
    }

    private function resetMovement(int $campaignId, int $sceneId, array $payload): bool
    {
        $ids = $this->tokenIds($payload['tokenIds'] ?? []);
        if ($ids) $this->assertSceneTokens($campaignId, $sceneId, $ids);
        return $this->movement->resetTokens($campaignId, $sceneId, $ids) > 0;
    }

    private function setMovement(int $campaignId, int $sceneId, array $auth, array $payload): bool
    {
        $tokenId = $this->positiveId($payload['tokenId'] ?? null, 'token_id_invalid');
        $range = CombatMovementValue::parse($payload['movementRange'] ?? null);
        $points = min($range, CombatMovementValue::parse($payload['movementPoints'] ?? null));
        $resetMode = (string) ($payload['movementResetMode'] ?? 'turn');
        $result = $this->tokens->update($campaignId, $sceneId, $tokenId, $auth, [
            'revision' => $payload['tokenRevision'] ?? null,
            'movementRange' => $range,
            'movementSpent' => $range - $points,
            'movementResetMode' => $resetMode,
        ]);
        return isset($result['token']);
    }

    private function context(int $campaignId, int $sceneId, array $auth): bool
    {
        $result = $this->scenes->getScene($campaignId, $sceneId, $auth);
        return $this->sceneAccess->canManage($auth, $campaignId, $sceneId, $result['capabilities']);
    }

    private function snapshot(int $campaignId, int $sceneId, array $auth, bool $canManage): array
    {
        $combat = $this->combat($campaignId, $sceneId);
        $rows = $combat ? $this->combatants((int) $combat['id']) : [];
        $tokens = $this->tokens->list($campaignId, $sceneId, $auth)['items'];
        return CombatStatePresenter::present($combat, $rows, $tokens, $canManage);
    }

    private function combat(int $campaignId, int $sceneId): ?array
    {
        return $this->db->table('scene_combats')->where([
            'campaign_id' => $campaignId, 'scene_id' => $sceneId,
        ])->get()->getRowArray() ?: null;
    }

    private function sceneIsVisible(int $campaignId, int $sceneId): bool
    {
        $scene = $this->db->table('scenes')->select('is_visible')->where([
            'id' => $sceneId, 'campaign_id' => $campaignId, 'deleted_at' => null,
        ])->get()->getRowArray();
        return $scene && !empty($scene['is_visible']);
    }

    private function combatants(int $combatId): array
    {
        return $this->db->table('scene_combatants')->where('combat_id', $combatId)
            ->orderBy('initiative', 'DESC')->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'ASC')->get()->getResultArray();
    }

    private function requiredCombat(int $campaignId, int $sceneId, array $payload, bool $active = false): array
    {
        $combat = $this->combat($campaignId, $sceneId);
        if (!$combat) throw new CombatException('combat_not_found', 'Combat was not found.', 404);
        if ($active && empty($combat['active'])) throw new CombatException('combat_inactive', 'Combat is not active.', 422);
        if ((int) ($payload['revision'] ?? 0) !== (int) $combat['revision']) {
            throw new CombatException('revision_conflict', 'Combat changed since it was loaded.', 409, [
                'currentRevision' => (int) $combat['revision'],
            ]);
        }
        return $combat;
    }

    private function writeCombat(array $combat, array $changes): void
    {
        $this->db->table('scene_combats')->set($changes)
            ->set('updated_at', date('Y-m-d H:i:s'))->set('revision', 'revision + 1', false)
            ->where('id', $combat['id'])->where('revision', $combat['revision'])->update();
        if ($this->db->affectedRows() !== 1) {
            throw new CombatException('revision_conflict', 'Combat changed since it was loaded.', 409);
        }
    }

    private function assertSceneTokens(int $campaignId, int $sceneId, array $ids): void
    {
        $rows = $this->db->table('scene_tokens')->select('id')->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('deleted_at', null)->whereIn('id', $ids)
            ->get()->getResultArray();
        if (count($rows) !== count($ids)) {
            throw new CombatException('token_not_found', 'A selected token was not found.', 404);
        }
    }

    private function tokenIds($value): array
    {
        if (!is_array($value) || count($value) > 200) {
            throw new CombatException('token_ids_invalid', 'Token list is invalid.', 422);
        }
        return array_values(array_unique(array_map(
            fn ($id): int => $this->positiveId($id, 'token_ids_invalid'), $value
        )));
    }

    private function positiveId($value, string $code): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new CombatException($code, 'A positive id is required.', 422);
        return (int) $id;
    }

    private function finishTransaction(): void
    {
        if ($this->db->transStatus() === false) {
            throw new CombatException('combat_write_failed', 'Combat could not be saved.', 500);
        }
        $this->db->transCommit();
    }
}
