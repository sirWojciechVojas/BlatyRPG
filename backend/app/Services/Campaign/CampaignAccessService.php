<?php

namespace App\Services\Campaign;

use App\Models\CampaignMemberModel;
use App\Models\CampaignModel;

class CampaignAccessService
{
    private $campaigns;
    private $members;
    private $policy;

    public function __construct(
        ?CampaignModel $campaigns = null,
        ?CampaignMemberModel $members = null,
        ?CampaignAccessPolicy $policy = null
    ) {
        $this->campaigns = $campaigns ?: new CampaignModel();
        $this->members = $members ?: new CampaignMemberModel();
        $this->policy = $policy ?: new CampaignAccessPolicy();
    }

    public function forCampaign(array $auth, int $campaignId): array
    {
        $campaign = $campaignId > 0 ? $this->campaigns->find($campaignId) : null;
        if (!$campaign) {
            return [
                'exists' => false,
                'allowed' => false,
                'capabilities' => $this->publicCapabilities([]),
            ];
        }

        $membership = null;
        $userId = (int) ($auth['user_id'] ?? 0);
        if ($userId > 0) {
            $membership = $this->members
                ->where('campaign_id', $campaignId)
                ->where('user_id', $userId)
                ->first();
        }
        $capabilities = $this->policy->evaluate($auth, $campaign, $membership);

        return [
            'exists' => true,
            'allowed' => $capabilities['canAccess'],
            'capabilities' => $this->publicCapabilities($capabilities),
        ];
    }

    private function publicCapabilities(array $capabilities): array
    {
        return [
            'canManage' => !empty($capabilities['canManage']),
            'canViewHidden' => !empty($capabilities['canViewHidden']),
        ];
    }
}
