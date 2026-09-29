<?php

namespace App\Services\Fog;

use App\Models\CampaignMemberModel;
use App\Models\SceneFogStateModel;
use App\Models\SceneModel;
use App\Models\SceneTokenModel;
use App\Services\Campaign\CampaignAccessService;
use App\Services\Token\TokenAccessService;
use CodeIgniter\Database\BaseConnection;

final class SceneFogService
{
    private $db;
    private $states;
    private $scenes;
    private $members;
    private $access;
    private $tokens;
    private $tokenAccess;
    private $visibility;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneFogStateModel $states = null,
        ?SceneModel $scenes = null,
        ?CampaignMemberModel $members = null,
        ?CampaignAccessService $access = null,
        ?SceneTokenModel $tokens = null,
        ?TokenAccessService $tokenAccess = null,
        ?SceneVisibilityService $visibility = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->states = $states ?: new SceneFogStateModel($this->db);
        $this->scenes = $scenes ?: new SceneModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->access = $access ?: new CampaignAccessService();
        $this->tokens = $tokens ?: new SceneTokenModel($this->db);
        $this->tokenAccess = $tokenAccess ?: new TokenAccessService($this->db);
        $this->visibility = $visibility ?: new SceneVisibilityService($this->db);
    }

    public function get(int $campaignId, int $sceneId, array $auth, ?int $targetUserId = null): array
    {
        [$scene, $caps, $userId] = $this->context($campaignId, $sceneId, $auth, $targetUserId);
        return $this->present($this->find($sceneId, $userId), $scene, $userId, $caps['canManage']);
    }

    public function patch(int $campaignId, int $sceneId, array $auth, array $payload): array
    {
        $target = isset($payload['targetUserId']) ? (int) $payload['targetUserId'] : null;
        [$scene, $caps, $userId] = $this->context($campaignId, $sceneId, $auth, $target);
        $mode = strtolower(trim((string) ($payload['mode'] ?? 'explore')));
        $managerModes = ['reveal', 'hide', 'revealall', 'hideall', 'reset'];
        if (in_array($mode, $managerModes, true) && !$caps['canManage']) {
            throw new FogException('forbidden', 'Only a game master can edit exploration manually.', 403);
        }
        if (!in_array($mode, array_merge(['explore'], $managerModes), true)) {
            throw new FogException('validation_failed', 'Fog operation is invalid.', 422);
        }

        $cellSize = $this->cellSize($scene, $payload['cellSize'] ?? null);
        $columns = (int) ceil((int) $scene['width'] / $cellSize);
        $rows = (int) ceil((int) $scene['height'] / $cellSize);
        $maximum = max(1, $columns * $rows);
        $incoming = FogRangeSet::normalize($payload['ranges'] ?? [], $maximum);
        if (count($incoming) > 12000) {
            throw new FogException('validation_failed', 'Fog patch contains too many ranges.', 422);
        }
        if ($mode === 'explore' && !$caps['canManage']) {
            $incoming = $this->authorizedExploration(
                $auth,
                $campaignId,
                $scene,
                $incoming,
                $cellSize,
                $columns,
                $maximum
            );
        }

        $existing = $this->find($sceneId, $userId);
        if ($existing && (int) $existing['cell_size'] !== $cellSize) {
            throw new FogException('fog_resolution_conflict', 'Fog cell size does not match the stored state.', 409);
        }
        $explored = $this->decode($existing['explored_ranges_json'] ?? null, $maximum);
        $hidden = $this->decode($existing['forced_hidden_ranges_json'] ?? null, $maximum);
        $before = json_encode([$explored, $hidden]);
        $all = [[0, $maximum - 1]];

        if ($mode === 'explore') {
            $explored = FogRangeSet::union($explored, FogRangeSet::subtract($incoming, $hidden, $maximum), $maximum);
        } elseif ($mode === 'reveal') {
            $hidden = FogRangeSet::subtract($hidden, $incoming, $maximum);
            $explored = FogRangeSet::union($explored, $incoming, $maximum);
        } elseif ($mode === 'hide') {
            $explored = FogRangeSet::subtract($explored, $incoming, $maximum);
            $hidden = FogRangeSet::union($hidden, $incoming, $maximum);
        } elseif ($mode === 'revealall') {
            $explored = $all;
            $hidden = [];
        } elseif ($mode === 'hideall') {
            $explored = [];
            $hidden = $all;
        } else {
            $explored = [];
            $hidden = [];
        }

        $changed = $before !== json_encode([$explored, $hidden]);
        if ($changed) {
            $existing = $this->write($campaignId, $sceneId, $userId, $cellSize, $explored, $hidden, $existing);
        }
        return $this->present($existing, $scene, $userId, $caps['canManage'])
            + ['changed' => $changed, 'mode' => $mode];
    }

    public function resetAll(int $campaignId, int $sceneId, array $auth): array
    {
        [$scene, $caps, $userId] = $this->context($campaignId, $sceneId, $auth, null);
        if (empty($caps['canManage'])) {
            throw new FogException('forbidden', 'Only a game master can reset Fog of War.', 403);
        }
        $this->db->table('scene_fog_states')
            ->where('campaign_id', $campaignId)
            ->where('scene_id', $sceneId)
            ->delete();
        $cellSize = $this->cellSize($scene, null);
        $state = $this->write(
            $campaignId,
            $sceneId,
            $userId,
            $cellSize,
            [],
            [],
            null
        );
        return array_merge(
            $this->present($state, $scene, $userId, true),
            ['changed' => true, 'mode' => 'reset', 'resetAll' => true, 'shared' => true]
        );
    }

    private function context(int $campaignId, int $sceneId, array $auth, ?int $target): array
    {
        $access = $this->access->forCampaign($auth, $campaignId);
        if (!$access['exists']) throw new FogException('campaign_not_found', 'Campaign was not found.', 404);
        if (!$access['allowed']) throw new FogException('forbidden', 'You cannot access this campaign.', 403);
        $scene = $this->scenes->where('campaign_id', $campaignId)->where('id', $sceneId)->first();
        if (!$scene) throw new FogException('scene_not_found', 'Scene was not found.', 404);
        $current = (int) ($auth['user_id'] ?? 0);
        $userId = $target ?: $current;
        if ($userId < 1) throw new FogException('forbidden', 'A player identity is required.', 403);
        if ($userId !== $current && empty($access['capabilities']['canManage'])) {
            throw new FogException('forbidden', 'You cannot inspect another player fog state.', 403);
        }
        if ($userId !== $current && !$this->members->where('campaign_id', $campaignId)->where('user_id', $userId)->first()) {
            throw new FogException('player_not_found', 'Player is not a member of this campaign.', 404);
        }
        if (($scene['fog_exploration_mode'] ?? 'individual') === 'shared') {
            $campaign = $this->db->table('campaigns')
                ->select('game_master_id')
                ->where('id', $campaignId)
                ->where('deleted_at', null)
                ->get()->getRowArray();
            $sharedUserId = (int) ($campaign['game_master_id'] ?? 0);
            if ($sharedUserId > 0) $userId = $sharedUserId;
        }
        return [$scene, $access['capabilities'], $userId];
    }

    private function cellSize(array $scene, $requested): int
    {
        $width = max(1, (int) $scene['width']);
        $height = max(1, (int) $scene['height']);
        $minimum = max(
            32,
            (int) ceil(max($width, $height) / 2048),
            (int) ceil(sqrt(($width * $height) / 1048576))
        );
        $value = filter_var($requested, FILTER_VALIDATE_INT, ['options' => ['min_range' => $minimum, 'max_range' => 512]]);
        return $value === false ? max(64, $minimum) : (int) $value;
    }

    private function find(int $sceneId, int $userId): ?array
    {
        return $this->states->where('scene_id', $sceneId)->where('user_id', $userId)->first();
    }

    private function decode($json, int $maximum): array
    {
        $value = is_string($json) ? json_decode($json, true) : $json;
        return FogRangeSet::normalize(is_array($value) ? $value : [], $maximum);
    }

    private function authorizedExploration(
        array $auth,
        int $campaignId,
        array $scene,
        array $incoming,
        int $cellSize,
        int $columns,
        int $maximum
    ): array {
        if (empty($scene['fog_enabled']) || empty($scene['dynamic_vision'])
            || empty($scene['exploration_memory'])
            || ($scene['fog_exploration_mode'] ?? 'individual') === 'none'
            || !$incoming) return [];
        $cellCount = 0;
        foreach ($incoming as [$start, $end]) $cellCount += $end - $start + 1;
        if ($cellCount > 50000) {
            throw new FogException(
                'validation_failed',
                'Fog exploration patch is too large to authorize.',
                422
            );
        }
        $sources = array_values(array_filter(
            $this->tokens->where('campaign_id', $campaignId)
                ->where('scene_id', (int) $scene['id'])->where('hidden', 0)->findAll(),
            fn (array $token): bool => $this->tokenAccess->canView(
                $auth,
                $campaignId,
                $token,
                false
            ) && $this->tokenAccess->canControl($auth, $campaignId, $token, false)
        ));
        if (!$sources) return [];
        $candidates = $sources;
        foreach ($incoming as [$start, $end]) {
            for ($index = $start; $index <= $end; $index++) {
                $candidates[] = [
                    '_fog_index' => $index,
                    'x' => ($index % $columns + 0.5) * $cellSize,
                    'y' => (intdiv($index, $columns) + 0.5) * $cellSize,
                    'width' => 0,
                    'height' => 0,
                    'vision_json' => [],
                    'controlled_by_json' => ['mode' => 'gm', 'userIds' => []],
                ];
            }
        }
        $authorized = [];
        foreach ($this->visibility->filter($auth, $campaignId, $scene, $candidates) as $candidate) {
            if (isset($candidate['_fog_index'])) {
                $index = (int) $candidate['_fog_index'];
                if ($index >= 0 && $index < $maximum) $authorized[] = [$index, $index];
            }
        }
        return FogRangeSet::intersect($incoming, $authorized, $maximum);
    }

    private function write(int $campaignId, int $sceneId, int $userId, int $cellSize, array $explored, array $hidden, ?array $existing): array
    {
        $data = [
            'campaign_id' => $campaignId, 'scene_id' => $sceneId, 'user_id' => $userId,
            'cell_size' => $cellSize, 'explored_ranges_json' => json_encode($explored),
            'forced_hidden_ranges_json' => json_encode($hidden),
        ];
        if (!$existing) {
            $data['revision'] = 1;
            $this->states->insert($data);
        } else {
            $this->db->table('scene_fog_states')->set($data)
                ->set('revision', 'revision + 1', false)->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id', (int) $existing['id'])->update();
        }
        return $this->find($sceneId, $userId);
    }

    private function present(?array $state, array $scene, int $userId, bool $canManage): array
    {
        $cellSize = $state ? (int) $state['cell_size'] : $this->cellSize($scene, null);
        $maximum = max(1, (int) ceil((int) $scene['width'] / $cellSize)
            * (int) ceil((int) $scene['height'] / $cellSize));
        $visionTokenIds = [];
        foreach ($this->tokens->where('campaign_id', (int) $scene['campaign_id'])
            ->where('scene_id', (int) $scene['id'])->findAll() as $token) {
            if ($this->tokenAccess->canControl(
                ['user_id' => $userId],
                (int) $scene['campaign_id'],
                $token,
                false
            )) {
                $visionTokenIds[] = (int) $token['id'];
            }
        }
        return [
            'sceneId' => (int) $scene['id'], 'userId' => $userId, 'cellSize' => $cellSize,
            'exploredRanges' => $this->decode($state['explored_ranges_json'] ?? null, $maximum),
            'forcedHiddenRanges' => $this->decode($state['forced_hidden_ranges_json'] ?? null, $maximum),
            'revision' => (int) ($state['revision'] ?? 0),
            'explorationMode' => (string) ($scene['fog_exploration_mode'] ?? 'individual'),
            'shared' => ($scene['fog_exploration_mode'] ?? 'individual') === 'shared',
            'visionTokenIds' => $visionTokenIds,
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
