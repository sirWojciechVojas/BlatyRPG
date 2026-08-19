<?php

namespace App\Services\Shop;

use App\Models\CampaignModel;
use App\Models\ShopOwnerClaimModel;

class ShopAuthorizationService
{
    private $authContextService;
    private $ownerClaimModel;
    private $campaignModel;

    public function __construct()
    {
        $this->authContextService = new AuthContextService();
        $this->ownerClaimModel = new ShopOwnerClaimModel();
        $this->campaignModel = new CampaignModel();
    }

    public function assertCampaignAccess(array $authContext, int $campaignId): array
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        if ($role === 'admin' || !empty($authContext['development_access'])) {
            return ['ok' => true];
        }

        $userId = (int) ($authContext['user_id'] ?? 0);
        if (!$userId) {
            return $this->forbidden('User context is missing.');
        }

        if ($role === 'gm') {
            $campaign = $this->campaignModel
                ->where('id', $campaignId)
                ->where('game_master_id', $userId)
                ->first();
            return $campaign ? ['ok' => true] : $this->forbidden('Campaign is not assigned to current GM.');
        }

        $claim = $this->ownerClaimModel
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->first();
        return $claim ? ['ok' => true] : $this->forbidden('Campaign is not assigned to current user.');
    }

    public function assertGm(array $authContext, ?int $campaignId = null): array
    {
        $role = strtolower((string) ($authContext['role'] ?? ''));
        if ($role === 'admin') {
            return ['ok' => true];
        }
        if ($role === 'gm' && !empty($authContext['development_access'])) {
            return ['ok' => true];
        }
        if ($role === 'gm' && $campaignId !== null) {
            return $this->assertCampaignAccess($authContext, $campaignId);
        }

        return $this->forbidden('GM permissions are required.');
    }

    public function assertOwnerAccess(array $authContext, int $campaignId, string $ownerCode): array
    {
        if ($this->authContextService->isGmOrAdmin($authContext)) {
            return $this->assertGm($authContext, $campaignId);
        }

        $selectedOwnerCodes = array_map(
            'strtoupper',
            (array) ($authContext['selected_owner_codes'] ?? [])
        );
        if (!empty($authContext['development_access'])) {
            return in_array(strtoupper($ownerCode), $selectedOwnerCodes, true)
                ? ['ok' => true]
                : $this->forbidden('Selected character does not match owner code.', 'forbidden_owner');
        }

        $userId = (int) ($authContext['user_id'] ?? 0);
        if (!$userId) {
            return $this->forbidden('User context is missing.');
        }

        $claim = $this->ownerClaimModel
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->where('owner_code', strtoupper($ownerCode))
            ->first();

        if ($claim) {
            return ['ok' => true];
        }

        return $this->forbidden('Owner code is not assigned to current user.', 'forbidden_owner');
    }

    public function resolveOwnerCode(array $authContext, int $campaignId, string $requested = ''): string
    {
        $requested = strtoupper(trim($requested));
        if ($this->authContextService->isGmOrAdmin($authContext)) {
            return $requested ?: 'BG1';
        }

        $selected = array_values(array_filter(array_map(
            'strtoupper',
            (array) ($authContext['selected_owner_codes'] ?? [])
        )));
        if (!empty($authContext['development_access'])) {
            return $selected[0] ?? $requested;
        }

        $userId = (int) ($authContext['user_id'] ?? 0);
        if (!$userId) {
            return $requested;
        }
        $claims = $this->ownerClaimModel
            ->where('campaign_id', $campaignId)
            ->where('user_id', $userId)
            ->orderBy('owner_code', 'ASC')
            ->findAll();
        $ownerCodes = array_map(static fn (array $claim): string => strtoupper((string) $claim['owner_code']), $claims);
        if ($requested && in_array($requested, $ownerCodes, true)) {
            return $requested;
        }
        return $ownerCodes[0] ?? $requested;
    }

    private function forbidden(string $message, string $code = 'forbidden'): array
    {
        return [
            'ok' => false,
            'code' => $code,
            'message' => $message,
            'status' => 403,
        ];
    }
}
