<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Services\Campaign\CampaignException;
use App\Services\Combat\SceneCombatService;
use App\Services\Fog\FogException;
use App\Services\Fog\SceneFogService;
use App\Services\Light\SceneLightService;
use App\Services\Realtime\RealtimePrincipalService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneService;
use App\Services\Tile\SceneTileService;
use App\Services\Token\SceneTokenService;
use App\Services\Token\TokenMovementRequestService;
use App\Services\Wall\SceneWallService;
use CodeIgniter\API\ResponseTrait;

/**
 * Produces one permission-filtered scene snapshot for a realtime reconnect.
 * The domain services remain the source of authorization and presentation.
 */
class InternalRealtimeSceneSnapshotController extends BaseController
{
    use ResponseTrait;

    private $principals;
    private $scenes;
    private $tokens;
    private $walls;
    private $lights;
    private $tiles;
    private $combats;
    private $fog;
    private $movementRequests;

    public function __construct(
        ?RealtimePrincipalService $principals = null,
        ?SceneService $scenes = null,
        ?SceneTokenService $tokens = null,
        ?SceneWallService $walls = null,
        ?SceneLightService $lights = null,
        ?SceneTileService $tiles = null,
        ?SceneCombatService $combats = null,
        ?SceneFogService $fog = null,
        ?TokenMovementRequestService $movementRequests = null
    ) {
        $this->principals = $principals ?: new RealtimePrincipalService();
        $this->scenes = $scenes ?: new SceneService();
        $this->tokens = $tokens ?: new SceneTokenService();
        $this->walls = $walls ?: new SceneWallService();
        $this->lights = $lights ?: new SceneLightService();
        $this->tiles = $tiles ?: new SceneTileService();
        $this->combats = $combats ?: new SceneCombatService();
        $this->fog = $fog ?: new SceneFogService();
        $this->movementRequests = $movementRequests ?: new TokenMovementRequestService();
    }

    public function show($campaignId = null, $sceneId = null)
    {
        try {
            $campaign = $this->positiveId($campaignId);
            $sceneId = $this->positiveId($sceneId);
            $auth = $this->principals->resolve(
                $this->request->getHeaderLine('Authorization'),
                $this->request->getHeaderLine('X-Realtime-Client-Instance'),
                $campaign
            );
            $scenes = $this->scenes->listScenes($campaign, $auth);
            $scene = $this->scenes->getScene($campaign, $sceneId, $auth);

            return $this->respond([
                'snapshot' => [
                    'scenes' => $scenes,
                    'scene' => $scene['scene'],
                    'capabilities' => $scene['capabilities'],
                    'tokens' => $this->tokens->list($campaign, $sceneId, $auth),
                    'walls' => $this->walls->list($campaign, $sceneId, $auth),
                    'lights' => $this->lights->list($campaign, $sceneId, $auth),
                    'tiles' => $this->tiles->list($campaign, $sceneId, $auth),
                    'combat' => $this->combats->get($campaign, $sceneId, $auth),
                    'fog' => $this->fog->get($campaign, $sceneId, $auth),
                    'movementRequests' => $this->movementRequests->listPending($campaign, $auth),
                ],
            ]);
        } catch (CampaignException | SceneException | FogException $exception) {
            return $this->failure($exception);
        }
    }

    private function positiveId($value): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException('not_found', 'Resource was not found.', 404);
        }
        return (int) $id;
    }

    private function failure($exception)
    {
        $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
        if ($exception->details()) {
            $payload['errors'] = $exception->details();
        }
        return $this->response->setStatusCode($exception->status())->setJSON($payload);
    }
}
