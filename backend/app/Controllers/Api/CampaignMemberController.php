<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignMemberService;

class CampaignMemberController extends CampaignApiController
{
    private $members;

    public function __construct()
    {
        parent::__construct();
        $this->members = new CampaignMemberService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->members->list($this->auth(), $this->positiveId($campaignId));
        });
    }

    public function update($campaignId = null, $userId = null)
    {
        return $this->execute(function () use ($campaignId, $userId): array {
            return $this->members->changeRole(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->positiveId($userId, 'member_not_found'),
                $this->jsonPayload()
            );
        });
    }

    public function delete($campaignId = null, $userId = null)
    {
        return $this->execute(function () use ($campaignId, $userId): array {
            return $this->members->remove(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->positiveId($userId, 'member_not_found')
            );
        });
    }
}
