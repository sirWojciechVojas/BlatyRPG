<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignException;
use App\Services\Token\TokenException;
use App\Services\Token\TokenTemplateService;

class TokenTemplateController extends CampaignApiController
{
    private $templates;

    public function __construct()
    {
        parent::__construct();
        $this->templates = new TokenTemplateService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(fn (): array => $this->templates->list(
            $this->positiveId($campaignId),
            $this->auth()
        ));
    }

    public function instantiate($campaignId = null, $sceneId = null)
    {
        return $this->execute(fn (): array => $this->templates->instantiate(
            $this->positiveId($campaignId),
            $this->positiveId($sceneId),
            $this->auth(),
            $this->jsonPayload()
        ), 201);
    }

    public function assetFile($campaignId = null, $assetId = null)
    {
        try {
            $result = $this->templates->asset(
                $this->positiveId($campaignId),
                $this->positiveId($assetId),
                $this->auth()
            );
            $asset = $result['asset'];
            if (!empty($result['url'])) {
                return $this->response->setStatusCode(302)->setHeader('Location', (string) $result['url'])
                    ->setHeader('Cache-Control', 'private, no-store');
            }
            return $this->response
                ->setHeader('Content-Type', (string) $asset['mime_type'])
                ->setHeader('Content-Length', (string) filesize($result['path']))
                ->setHeader('Cache-Control', 'private, max-age=3600')
                ->setHeader('Content-Disposition', 'inline; filename="' . addcslashes((string) $asset['original_name'], "\\\"") . '"')
                ->setBody((string) file_get_contents($result['path']));
        } catch (CampaignException $exception) {
            $payload = ['code' => $exception->errorCode(), 'message' => $exception->getMessage()];
            if ($exception->details()) $payload['errors'] = $exception->details();
            return $this->response->setStatusCode($exception->status())->setJSON($payload);
        }
    }
}
