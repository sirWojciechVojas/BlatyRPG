<?php

namespace App\Services\Token;

use App\Models\SceneTokenModel;
use App\Models\TokenMovementRequestModel;
use App\Models\UserModel;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Scene\SceneService;
use CodeIgniter\Database\BaseConnection;

final class TokenMovementRequestService
{
    private $db;
    private $requests;
    private $tokens;
    private $users;
    private $guard;
    private $scenes;
    private $access;
    private $grid;
    private $movement;
    private $tokenService;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->requests = new TokenMovementRequestModel($this->db);
        $this->tokens = new SceneTokenModel($this->db);
        $this->users = new UserModel($this->db);
        $this->guard = new CampaignGuardService();
        $this->scenes = new SceneService($this->db);
        $this->access = new TokenAccessService();
        $this->grid = new TokenGridPositionService();
        $this->movement = new TokenMovementService(null, $this->grid);
        $this->tokenService = new SceneTokenService($this->db);
    }

    public function listPending(int $campaignId, array $auth): array
    {
        $context = $this->guard->context($auth, $campaignId);
        $this->expireOld($campaignId);
        $query = $this->requests->where('campaign_id', $campaignId)
            ->where('status', 'pending');
        if (empty($context['isGameMaster'])) {
            $query->where('requested_by_user_id', (int) $context['user']['id']);
        }
        $items = $query->orderBy('created_at', 'DESC')->findAll(100);
        return [
            'items' => array_map(fn (array $row): array => $this->present($row), $items),
            'capabilities' => ['canResolve' => !empty($context['isGameMaster'])],
        ];
    }

    public function request(int $campaignId, array $auth, array $payload): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (!empty($context['isGameMaster'])) {
            throw new TokenException('movement_request_not_required',
                'A game master can move beyond the movement limit directly.', 422);
        }
        $sceneId = $this->positiveId($payload['sceneId'] ?? null);
        $tokenId = $this->positiveId($payload['tokenId'] ?? null);
        $revision = $this->positiveId($payload['revision'] ?? null);
        $scene = $this->scenes->getScene($campaignId, $sceneId, $context['auth'])['scene'];
        $token = $this->findToken($campaignId, $sceneId, $tokenId);
        $this->assertPlayerCanRequest($context['auth'], $campaignId, $token);
        if ((int) $token['revision'] !== $revision) {
            throw new TokenException('revision_conflict', 'Token changed since it was loaded.', 409,
                ['currentRevision' => (int) $token['revision']]);
        }
        TokenMovementBudget::assertAvailable($token);
        $target = $this->grid->snap($scene, [
            'x' => $this->coordinate($payload['x'] ?? null),
            'y' => $this->coordinate($payload['y'] ?? null),
        ], (float) $token['width'], (float) $token['height']);
        $waypoints = TokenMovementRoute::validate($payload['waypoints'] ?? []);
        $limit = $this->assertExceeded(
            $campaignId, $sceneId, $scene, $token, $target, $waypoints
        );
        $now = date('Y-m-d H:i:s');
        $userId = (int) $context['user']['id'];
        $this->db->table('token_movement_requests')->set([
            'status' => 'expired', 'updated_at' => $now, 'resolved_at' => $now,
        ])->where('campaign_id', $campaignId)->where('token_id', $tokenId)
            ->where('requested_by_user_id', $userId)->where('status', 'pending')->update();
        $data = [
            'campaign_id' => $campaignId, 'scene_id' => $sceneId, 'token_id' => $tokenId,
            'requested_by_user_id' => $userId,
            'origin_x' => $token['x'], 'origin_y' => $token['y'],
            'target_x' => $target['x'], 'target_y' => $target['y'],
            'waypoints_json' => $waypoints, 'cost' => $limit['cost'],
            'spent_at_request' => $limit['spent'], 'range_at_request' => $limit['range'],
            'token_revision' => $revision, 'status' => 'pending',
        ];
        if (!$this->requests->insert($data)) {
            throw new TokenException('movement_request_failed',
                'Movement request could not be saved.', 500);
        }
        return ['request' => $this->present($this->requests->find($this->requests->getInsertID()))];
    }

    public function resolve(int $campaignId, int $requestId, array $auth, string $decision): array
    {
        $context = $this->guard->context($auth, $campaignId);
        if (empty($context['isGameMaster'])) {
            throw new TokenException('forbidden', 'Only the game master can resolve movement requests.', 403);
        }
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw new TokenException('validation_failed', 'Movement decision is invalid.', 422,
                ['decision' => 'Use approve or reject.']);
        }
        $request = $this->findRequest($campaignId, $requestId);
        if ($decision === 'reject') {
            return $this->reject($request, (int) $context['user']['id']);
        }
        if (!$this->claim($requestId, (int) $context['user']['id'])) {
            throw new TokenException('movement_request_resolved',
                'Movement request has already been resolved.', 409);
        }
        try {
            $result = $this->tokenService->update(
                $campaignId, (int) $request['scene_id'], (int) $request['token_id'],
                $context['auth'], [
                    'revision' => (int) $request['token_revision'],
                    'x' => (float) $request['target_x'],
                    'y' => (float) $request['target_y'],
                ], $request['waypoints_json'] ?? []
            );
        } catch (\Throwable $exception) {
            $status = $exception instanceof TokenException
                && $exception->errorCode() === 'revision_conflict' ? 'expired' : 'pending';
            $this->requests->update($requestId, [
                'status' => $status,
                'resolved_by_user_id' => $status === 'expired' ? (int) $context['user']['id'] : null,
                'resolved_at' => $status === 'expired' ? date('Y-m-d H:i:s') : null,
            ]);
            throw $exception;
        }
        $this->requests->update($requestId, [
            'status' => 'approved', 'resolved_at' => date('Y-m-d H:i:s'),
        ]);
        return $result + ['request' => $this->present($this->requests->find($requestId))];
    }

    private function assertExceeded(
        int $campaignId, int $sceneId, array $scene, array $token,
        array $target, array $waypoints
    ): array {
        try {
            $this->movement->apply(
                $campaignId, $sceneId, $scene, $token,
                ['x' => $target['x'], 'y' => $target['y']], $waypoints, false
            );
        } catch (TokenException $exception) {
            if ($exception->errorCode() === 'movement_limit_exceeded') {
                return $exception->details();
            }
            throw $exception;
        }
        throw new TokenException('movement_request_not_required',
            'Movement is within the available movement points.', 422);
    }

    private function assertPlayerCanRequest(array $auth, int $campaignId, array $token): void
    {
        if (!empty($token['locked'])) {
            throw new TokenException('token_locked', 'This token is locked by the game master.', 403);
        }
        if (!$this->access->canView($auth, $campaignId, $token, false)
            || !$this->access->canControl($auth, $campaignId, $token, false)) {
            throw new TokenException('forbidden', 'You cannot move this token.', 403);
        }
    }

    private function reject(array $request, int $userId): array
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('token_movement_requests')->set([
            'status' => 'rejected', 'resolved_by_user_id' => $userId,
            'resolved_at' => $now, 'updated_at' => $now,
        ])->where('id', $request['id'])->where('status', 'pending')->update();
        if ($this->db->affectedRows() !== 1) {
            throw new TokenException('movement_request_resolved',
                'Movement request has already been resolved.', 409);
        }
        return ['request' => $this->present($this->requests->find($request['id']))];
    }

    private function claim(int $requestId, int $userId): bool
    {
        $this->db->table('token_movement_requests')->set([
            'status' => 'processing', 'resolved_by_user_id' => $userId,
            'updated_at' => date('Y-m-d H:i:s'),
        ])->where('id', $requestId)->where('status', 'pending')->update();
        return $this->db->affectedRows() === 1;
    }

    private function present(array $row): array
    {
        $user = $this->users->find((int) $row['requested_by_user_id']);
        $token = $this->tokens->find((int) $row['token_id']);
        return [
            'id' => (int) $row['id'], 'campaignId' => (int) $row['campaign_id'],
            'sceneId' => (int) $row['scene_id'], 'tokenId' => (int) $row['token_id'],
            'tokenName' => (string) ($token['name'] ?? ''),
            'requestedByUserId' => (int) $row['requested_by_user_id'],
            'requesterName' => (string) ($user['username'] ?? ''),
            'origin' => ['x' => (float) $row['origin_x'], 'y' => (float) $row['origin_y']],
            'target' => ['x' => (float) $row['target_x'], 'y' => (float) $row['target_y']],
            'waypoints' => $row['waypoints_json'] ?? [],
            'cost' => (float) $row['cost'], 'spent' => (float) $row['spent_at_request'],
            'range' => (float) $row['range_at_request'],
            'status' => (string) $row['status'], 'createdAt' => $row['created_at'] ?? null,
        ];
    }

    private function findToken(int $campaignId, int $sceneId, int $tokenId): array
    {
        $row = $this->tokens->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)->where('id', $tokenId)->first();
        if (!$row) throw new TokenException('token_not_found', 'Token was not found.', 404);
        return $row;
    }

    private function findRequest(int $campaignId, int $requestId): array
    {
        $row = $this->requests->where('campaign_id', $campaignId)
            ->where('id', $requestId)->where('status', 'pending')->first();
        if (!$row) throw new TokenException('movement_request_not_found',
            'Pending movement request was not found.', 404);
        return $row;
    }

    private function expireOld(int $campaignId): void
    {
        $cutoff = date('Y-m-d H:i:s', time() - 86400);
        $this->db->table('token_movement_requests')->set([
            'status' => 'expired', 'resolved_at' => date('Y-m-d H:i:s'),
        ])->where('campaign_id', $campaignId)->where('status', 'pending')
            ->where('created_at <', $cutoff)->update();
    }

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new TokenException('validation_failed', 'Identifier is invalid.', 422);
        return (int) $id;
    }

    private function coordinate($value): float
    {
        if (!is_numeric($value) || !is_finite((float) $value)
            || abs((float) $value) > 1000000) {
            throw new TokenException('validation_failed', 'Movement coordinates are invalid.', 422);
        }
        return (float) $value;
    }
}
