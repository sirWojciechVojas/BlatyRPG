<?php

namespace App\Controllers\Api;

use App\Services\Campaign\CampaignInvitationService;

class CampaignInvitationController extends CampaignApiController
{
    private $invitations;

    public function __construct()
    {
        parent::__construct();
        $this->invitations = new CampaignInvitationService();
    }

    public function index($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->invitations->listCampaign(
                $this->auth(),
                $this->positiveId($campaignId)
            );
        });
    }

    public function mine()
    {
        return $this->execute(function (): array {
            return $this->invitations->listMine($this->auth());
        });
    }

    public function create($campaignId = null)
    {
        return $this->execute(function () use ($campaignId): array {
            return $this->invitations->invite(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->jsonPayload()
            );
        }, 201);
    }

    public function accept($invitationId = null)
    {
        return $this->respondToInvitation($invitationId, true);
    }

    public function reject($invitationId = null)
    {
        return $this->respondToInvitation($invitationId, false);
    }

    public function revoke($campaignId = null, $invitationId = null)
    {
        return $this->execute(function () use ($campaignId, $invitationId): array {
            return $this->invitations->revoke(
                $this->auth(),
                $this->positiveId($campaignId),
                $this->positiveId($invitationId, 'invitation_not_found')
            );
        });
    }

    private function respondToInvitation($invitationId, bool $accept)
    {
        return $this->execute(function () use ($invitationId, $accept): array {
            return $this->invitations->respond(
                $this->auth(),
                $this->positiveId($invitationId, 'invitation_not_found'),
                $accept
            );
        });
    }
}
