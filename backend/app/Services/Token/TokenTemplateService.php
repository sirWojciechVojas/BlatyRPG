<?php

namespace App\Services\Token;

use App\Models\TokenTemplateAssetModel;
use App\Models\TokenTemplateModel;
use App\Services\Campaign\CampaignAccessService;
use App\Services\Scene\SceneException;
use App\Services\Scene\SceneResourceAccessService;
use App\Services\Scene\SceneService;
use App\Services\Media\MediaException;
use App\Services\Media\MediaService;
use CodeIgniter\Database\BaseConnection;

final class TokenTemplateService
{
    private $db;
    private $templates;
    private $assets;
    private $access;
    private $scenes;
    private $sceneAccess;
    private $tokens;
    private $storage;
    private $media;

    public function __construct(
        ?BaseConnection $db = null,
        ?TokenTemplateModel $templates = null,
        ?TokenTemplateAssetModel $assets = null,
        ?CampaignAccessService $access = null,
        ?SceneService $scenes = null,
        ?SceneResourceAccessService $sceneAccess = null,
        ?SceneTokenService $tokens = null,
        ?TokenTemplateAssetStorage $storage = null,
        ?MediaService $media = null
    ) {
        $db = $db ?: \Config\Database::connect();
        $this->db = $db;
        $this->templates = $templates ?: new TokenTemplateModel($db);
        $this->assets = $assets ?: new TokenTemplateAssetModel($db);
        $this->access = $access ?: new CampaignAccessService();
        $this->scenes = $scenes ?: new SceneService($db);
        $this->sceneAccess = $sceneAccess ?: new SceneResourceAccessService();
        $this->tokens = $tokens ?: new SceneTokenService($db);
        $this->storage = $storage ?: new TokenTemplateAssetStorage();
        $this->media = $media ?: new MediaService($this->db);
    }

    public function list(int $campaignId, array $auth): array
    {
        $this->authorizeCampaign($campaignId, $auth, true);
        $rows = $this->templates->orderBy('name', 'ASC')->orderBy('id', 'ASC')->findAll();
        return [
            'items' => array_map(
                fn (array $row): array => $this->present($campaignId, $row),
                $rows
            ),
            'capabilities' => ['canCreateToken' => true],
        ];
    }

    public function instantiate(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $payload
    ): array {
        if (array_diff(array_keys($payload), ['templateId', 'centerX', 'centerY'])) {
            throw new TokenException('validation_failed', 'Template placement payload is invalid.', 422);
        }
        $templateId = filter_var($payload['templateId'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        foreach (['centerX', 'centerY'] as $field) {
            if (!array_key_exists($field, $payload)
                || !is_numeric($payload[$field])
                || !is_finite((float) $payload[$field])) {
                throw new TokenException('validation_failed', 'Template placement payload is invalid.', 422, [
                    $field => 'A finite scene coordinate is required.',
                ]);
            }
        }
        if ($templateId === false) {
            throw new TokenException('validation_failed', 'Template placement payload is invalid.', 422, [
                'templateId' => 'A valid template id is required.',
            ]);
        }
        $template = $this->templates->find((int) $templateId);
        if (!$template) {
            throw new TokenException('token_template_unavailable', 'Token template is no longer available.', 409);
        }
        try {
            $context = $this->scenes->getScene($campaignId, $sceneId, $auth);
        } catch (SceneException $exception) {
            throw new TokenException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->details());
        }
        if (!$this->sceneAccess->canManage(
            $auth,
            $campaignId,
            $sceneId,
            $context['capabilities']
        )) {
            throw new TokenException('forbidden', 'You cannot manage tokens on this scene.', 403);
        }
        $scene = $context['scene'];
        $gridSize = max(1.0, (float) ($scene['grid_size'] ?? $scene['gridSize'] ?? 100));
        $width = round((float) $template['width_cells'] * $gridSize, 3);
        $height = round((float) $template['height_cells'] * $gridSize, 3);
        $assetId = isset($template['image_asset_id']) ? (int) $template['image_asset_id'] : null;
        return $this->tokens->create(
            $campaignId,
            $sceneId,
            $auth,
            [
                'characterId' => null,
                'name' => (string) $template['name'],
                'imageUrl' => $assetId ? '' : (string) ($template['image_url'] ?? ''),
                'x' => (float) $payload['centerX'] - $width / 2,
                'y' => (float) $payload['centerY'] - $height / 2,
                'width' => $width,
                'height' => $height,
                'rotation' => (float) $template['rotation'],
                'facing' => (float) $template['facing'],
                'rotationHandleEnabled' => !empty($template['rotation_handle_enabled']),
                'facingHandleEnabled' => !empty($template['facing_handle_enabled']),
                'rotationFollowsFacing' => !empty($template['rotation_follows_facing']),
                'showInfoUnselected' => !empty($template['show_info_unselected']),
                'resourceBarPosition' => (string) $template['resource_bar_position'],
                'elevation' => (float) $template['elevation'],
                'disposition' => (string) $template['disposition'],
                'movementRange' => (float) $template['movement_range'],
                'movementSpent' => 0,
                'movementResetMode' => (string) $template['movement_reset_mode'],
                'resources' => TokenResourceValidator::stored($template['bars_json'] ?? []),
                'vision' => $this->vision($template['vision_json'] ?? []),
                'hidden' => false,
                'locked' => false,
                'statuses' => [],
            ],
            [
                'token_template_id' => (int) $template['id'],
                'token_template_asset_id' => $assetId,
            ]
        );
    }

    public function asset(int $campaignId, int $assetId, array $auth): array
    {
        $this->assertActiveParticipation($campaignId, $auth);
        $asset = $assetId > 0 ? $this->assets->find($assetId) : null;
        if (!$asset) throw new TokenException('token_template_asset_not_found', 'Token image was not found.', 404);
        if (!empty($asset['media_asset_id'])) {
            try {
                $media = $this->media->getTrusted((int) $asset['media_asset_id']);
            } catch (MediaException $exception) {
                throw new TokenException($exception->errorCode(), $exception->getMessage(), $exception->status(), $exception->errors());
            }
            return ['asset' => $asset, 'url' => $media['url']];
        }
        return [
            'asset' => $asset,
            'path' => $this->storage->path((string) $asset['storage_key']),
        ];
    }

    private function authorizeCampaign(int $campaignId, array $auth, bool $manage): void
    {
        $access = $this->access->forCampaign($auth, $campaignId);
        if (!$access['exists']) throw new TokenException('campaign_not_found', 'Campaign was not found.', 404);
        if (!$access['allowed'] || ($manage && empty($access['capabilities']['canManage']))) {
            throw new TokenException('forbidden', 'You cannot access the token template library.', 403);
        }
    }

    private function assertActiveParticipation(int $campaignId, array $auth): void
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId < 1 || !empty($auth['anonymous'])) {
            throw new TokenException('unauthorized', 'Authentication is required.', 401);
        }
        $campaign = $this->db->table('campaigns')->select('game_master_id')
            ->where('id', $campaignId)->where('deleted_at', null)
            ->get()->getRowArray();
        if (!$campaign) {
            throw new TokenException('campaign_not_found', 'Campaign was not found.', 404);
        }
        if ((int) ($campaign['game_master_id'] ?? 0) === $userId) return;
        $membership = $this->db->table('campaign_members')->select('id')
            ->where('campaign_id', $campaignId)->where('user_id', $userId)
            ->where('is_active', 1)->get()->getRowArray();
        if (!$membership) {
            throw new TokenException(
                'forbidden',
                'Active campaign participation is required to access this token image.',
                403
            );
        }
    }

    private function present(int $campaignId, array $row): array
    {
        $assetId = isset($row['image_asset_id']) ? (int) $row['image_asset_id'] : null;
        $url = $assetId
            ? '/api/campaigns/' . $campaignId . '/token-template-assets/' . $assetId . '/file'
            : (string) ($row['image_url'] ?? '');
        return TokenTemplatePresenter::present($row, $url);
    }

    private function vision($value): array
    {
        $validated = TokenVisionValidator::validate(is_array($value) ? $value : []);
        return $validated['valid'] ? $validated['data'] : TokenVisionValidator::validate([])['data'];
    }
}
