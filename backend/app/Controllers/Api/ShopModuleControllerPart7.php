<?php

namespace App\Controllers\Api;

use App\Services\Shop\ShopPricingPreviewService;

trait ShopModuleControllerPart7
{
    public function previewShopPricing($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) return $this->respondError($gmCheck);
        $payload = $this->request->getJSON(true) ?: [];
        $result = (new ShopPricingPreviewService())->preview(
            (int) $campaignId,
            (int) $shopId,
            $payload
        );
        if ($result === null) return $this->failNotFound('Shop profile not found.');
        return $this->response->setJSON($result);
    }

    public function getShopProfileHistory($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) return $this->respondError($gmCheck);
        return $this->response->setJSON([
            'items' => $this->profileService->history(
                (int) $campaignId,
                (int) $shopId,
                (int) ($this->request->getGet('limit') ?? 30)
            ),
        ]);
    }

    public function exportShopProfile($campaignId, $shopId)
    {
        $gmCheck = $this->authorizationService->assertGm($this->resolveAuth(), (int) $campaignId);
        if (!$gmCheck['ok']) return $this->respondError($gmCheck);
        $document = $this->profileService->exportPortable((int) $campaignId, (int) $shopId);
        if (!$document) return $this->failNotFound('Shop profile not found.');
        return $this->response->setJSON($document);
    }

    public function importShopProfile($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm($auth, (int) $campaignId);
        if (!$gmCheck['ok']) return $this->respondError($gmCheck);
        $document = $this->request->getJSON(true) ?: [];
        $profile = $this->profileService->importPortable(
            (int) $campaignId,
            (int) $shopId,
            $document,
            isset($auth['user_id']) ? (int) $auth['user_id'] : null
        );
        if (!$profile) {
            return $this->fail([
                'code' => 'invalid_profile_import',
                'message' => 'Unsupported or invalid profile document.',
            ], 422);
        }
        return $this->response->setJSON(['ok' => true, 'profile' => $profile]);
    }
}
