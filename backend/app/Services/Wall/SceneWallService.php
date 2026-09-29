<?php

namespace App\Services\Wall;

use App\Models\SceneWallModel;
use App\Services\Audio\AudioLibraryService;
use App\Services\Campaign\CampaignException;
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
    private $audioLibrary;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneWallModel $walls = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $access = null,
        ?WallPayloadValidator $validator = null,
        ?AudioLibraryService $audioLibrary = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->walls = $walls ?: new SceneWallModel($this->db);
        $this->scenes = $scenes ?: new SceneService($this->db);
        $this->access = $access ?: new SceneResourceAccessService();
        $this->validator = $validator ?: new WallPayloadValidator();
        $this->audioLibrary = $audioLibrary;
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
        $this->authorizeSoundConfig($campaignId, $auth, $data, $data);
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
        $this->authorizeSoundConfig(
            $campaignId,
            $auth,
            $validated['data'],
            array_merge($row, $validated['data'])
        );
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

    public function interact(
        int $campaignId,
        int $sceneId,
        int $wallId,
        array $auth,
        array $payload
    ): array {
        [, $capabilities] = $this->sceneContext($campaignId, $sceneId, $auth);
        $row = $this->find($campaignId, $sceneId, $wallId);
        $canManage = $this->canManage($auth, $campaignId, $sceneId, $capabilities);
        $doorType = (string) ($row['door_type'] ?? (
            in_array(($row['type'] ?? 'wall'), ['door', 'secret', 'window'], true)
                ? $row['type'] : 'none'
        ));
        if ($doorType === 'secret' && !$canManage) {
            throw new WallException('wall_not_found', 'Wall was not found.', 404);
        }
        if (!in_array($doorType, ['door', 'secret', 'window'], true)) {
            throw new WallException('not_a_door', 'This wall is not an operable portal.', 422);
        }
        if (!$canManage && empty($row['player_operable'])) {
            throw new WallException('forbidden', 'Players cannot operate this door.', 403);
        }
        $actingTokenIds = $this->actingTokenIds($payload);
        $this->assertActingTokensBelongToScene(
            $campaignId,
            $sceneId,
            $actingTokenIds
        );
        if ($doorType === 'secret' && $this->hasOtherUsersCharacterToken(
            $actingTokenIds,
            (int) ($auth['user_id'] ?? 0)
        )) {
            throw new WallException(
                'secret_door_player_token',
                'A secret door cannot be operated while a player-owned token is selected.',
                403
            );
        }
        $revision = filter_var($payload['revision'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($revision === false) {
            throw new WallException('validation_failed', 'Door revision is required.', 422);
        }
        $next = strtolower(trim((string) ($payload['doorState'] ?? '')));
        if (!in_array($next, ['open', 'closed', 'locked'], true)) {
            throw new WallException('validation_failed', 'Door state is invalid.', 422);
        }
        $current = (string) ($row['door_state'] ?? 'closed');
        $silent = false;
        if (array_key_exists('silent', $payload)) {
            $silentValue = filter_var(
                $payload['silent'],
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE
            );
            if ($silentValue === null) {
                throw new WallException('validation_failed', 'Silent mode is invalid.', 422);
            }
            $silent = (bool) $silentValue;
        }
        if ($silent && (!$canManage || $doorType !== 'secret' || $next !== 'open')) {
            throw new WallException(
                'forbidden',
                'Silent opening is available only to the game master for a secret portal.',
                403
            );
        }
        if ($next === 'locked' && !$canManage) {
            throw new WallException('forbidden', 'Only a game master can lock a door.', 403);
        }
        if ($current === 'locked' && !$canManage && $next !== 'locked') {
            throw new WallException('door_locked', 'The door is locked.', 423, [
                'wallId' => $wallId, 'sound' => 'lockedAttempt',
            ]);
        }
        if ($next !== $current) {
            $this->writeRevision($campaignId, $sceneId, $wallId, (int) $revision, [
                'door_state' => $next,
            ]);
        }
        $sound = $next === 'open' ? 'open' : ($next === 'locked' ? 'lock' : 'close');
        $result = [
            'wall' => WallPresenter::present(
                $this->find($campaignId, $sceneId, $wallId),
                $canManage
            ),
        ];
        if (!$silent) $result['sound'] = $sound;
        return $result;
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

    private function authorizeSoundConfig(
        int $campaignId,
        array $auth,
        array $data,
        array $wall
    ): void {
        if (!array_key_exists('sound_config_json', $data)) return;
        $config = $data['sound_config_json'];
        if (is_string($config)) $config = json_decode($config, true);
        if (!is_array($config)) return;
        $rules = is_array($config['rules'] ?? null) ? $config['rules'] : [];
        $doorType = (string) ($wall['door_type'] ?? 'none');
        foreach ($rules as $index => $rule) {
            if (($rule['trigger'] ?? 'proximityLoop') !== 'proximityLoop'
                && !in_array($doorType, ['door', 'secret', 'window'], true)) {
                throw new WallException(
                    'validation_failed',
                    'Only doors and windows support portal sound triggers.',
                    422,
                    ['soundConfig.rules.' . $index . '.trigger' => 'Choose the proximity loop trigger.']
                );
            }
        }
        $trackIds = array_values(array_unique(array_map(
            static fn (array $rule): int => (int) ($rule['trackId'] ?? 0),
            $rules
        )));
        $trackIds = array_values(array_filter($trackIds));
        if (!$trackIds) return;
        if (!$this->db->tableExists('audio_tracks')) {
            throw new WallException(
                'validation_failed',
                'The audio library is unavailable.',
                422,
                ['soundConfig' => 'Choose tracks from an available campaign library.']
            );
        }
        $builder = $this->db->table('audio_tracks')->select('id,source_type,provider,storage_key,status')
            ->whereIn('id', $trackIds)->where('status', 'ready')
            ->where('deleted_at', null);
        if ($this->db->fieldExists('media_asset_id', 'audio_tracks')) {
            $builder->select('media_asset_id');
        }
        $tracks = $builder->get()->getResultArray();
        $byId = array_column($tracks, null, 'id');
        foreach ($trackIds as $trackId) {
            $track = $byId[$trackId] ?? null;
            if (!$track || (empty($track['storage_key']) && empty($track['media_asset_id']))
                || strtolower((string) ($track['source_type'] ?? '')) === 'external'
                || strtolower((string) ($track['provider'] ?? '')) === 'youtube') {
                throw new WallException(
                    'validation_failed',
                    'The selected track cannot be used as spatial audio.',
                    422,
                    ['soundConfig.trackId' => 'Choose an uploaded or directly playable library track.']
                );
            }
            $attached = $this->db->table('campaign_audio_tracks')
                ->where('campaign_id', $campaignId)
                ->where('audio_track_id', $trackId)
                ->where('is_enabled', 1)->countAllResults() > 0;
            if ($attached) continue;
            try {
                ($this->audioLibrary ?: new AudioLibraryService($this->db))
                    ->attachTrack($auth, $campaignId, $trackId);
            } catch (CampaignException $exception) {
                throw new WallException(
                    $exception->errorCode(),
                    $exception->getMessage(),
                    $exception->status(),
                    $exception->details()
                );
            }
        }
    }

    private function actingTokenIds(array $payload): array
    {
        if (!array_key_exists('actingTokenIds', $payload)) return [];
        if (!is_array($payload['actingTokenIds']) || count($payload['actingTokenIds']) > 50) {
            throw new WallException(
                'validation_failed',
                'Selected token context is invalid.',
                422,
                ['actingTokenIds' => 'Provide at most 50 valid token identifiers.']
            );
        }
        $ids = [];
        foreach ($payload['actingTokenIds'] as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1],
            ]);
            if ($id === false) {
                throw new WallException(
                    'validation_failed',
                    'Selected token context is invalid.',
                    422,
                    ['actingTokenIds' => 'Every token identifier must be a positive integer.']
                );
            }
            $ids[(int) $id] = (int) $id;
        }
        return array_values($ids);
    }

    private function assertActingTokensBelongToScene(
        int $campaignId,
        int $sceneId,
        array $tokenIds
    ): void {
        if (!$tokenIds) return;
        $rows = $this->db->table('scene_tokens')->select('id')
            ->where('campaign_id', $campaignId)->where('scene_id', $sceneId)
            ->where('deleted_at', null)->whereIn('id', $tokenIds)
            ->get()->getResultArray();
        if (count($rows) !== count($tokenIds)) {
            throw new WallException(
                'validation_failed',
                'Selected token context is invalid for this scene.',
                422,
                ['actingTokenIds' => 'Choose tokens from the current scene.']
            );
        }
    }

    private function hasOtherUsersCharacterToken(array $tokenIds, int $userId): bool
    {
        if (!$tokenIds) return false;
        $rows = $this->db->table('scene_tokens tokens')
            ->select('characters.user_id')
            ->join('characters', 'characters.id = tokens.character_id', 'inner')
            ->whereIn('tokens.id', $tokenIds)
            ->where('tokens.deleted_at', null)
            ->get()->getResultArray();
        foreach ($rows as $row) {
            $ownerId = (int) ($row['user_id'] ?? 0);
            if ($ownerId > 0 && $ownerId !== $userId) return true;
        }
        return false;
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
