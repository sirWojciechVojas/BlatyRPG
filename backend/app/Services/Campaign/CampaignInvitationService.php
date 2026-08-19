<?php

namespace App\Services\Campaign;

use App\Models\CampaignInvitationModel;
use App\Models\CampaignMemberModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;

class CampaignInvitationService
{
    private $db;
    private $invitations;
    private $members;
    private $users;
    private $guard;
    private $memberService;
    private $validator;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignInvitationModel $invitations = null,
        ?CampaignMemberModel $members = null,
        ?UserModel $users = null,
        ?CampaignGuardService $guard = null,
        ?CampaignMemberService $memberService = null,
        ?CampaignInvitationValidator $validator = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->invitations = $invitations ?: new CampaignInvitationModel($this->db);
        $this->members = $members ?: new CampaignMemberModel($this->db);
        $this->users = $users ?: new UserModel($this->db);
        $this->guard = $guard ?: new CampaignGuardService();
        $this->memberService = $memberService
            ?: new CampaignMemberService($this->members, $this->guard, $this->db);
        $this->validator = $validator ?: new CampaignInvitationValidator();
    }

    public function listCampaign(array $auth, int $campaignId): array
    {
        $context = $this->guard->requireManage($auth, $campaignId);
        $this->expirePending();
        $rows = $this->query()
            ->where('campaign_invitations.campaign_id', $campaignId)
            ->orderBy('campaign_invitations.created_at', 'DESC')
            ->findAll();
        return [
            'items' => array_map([CampaignInvitationPresenter::class, 'present'], $rows),
            'capabilities' => $context['capabilities'],
        ];
    }

    public function listMine(array $auth): array
    {
        $user = $this->authenticatedUser($auth);
        $this->expirePending();
        $rows = $this->query()
            ->where('campaign_invitations.invitee_user_id', (int) $user['id'])
            ->where('campaign_invitations.status', 'pending')
            ->orderBy('campaign_invitations.created_at', 'DESC')
            ->findAll();
        return ['items' => array_map([CampaignInvitationPresenter::class, 'present'], $rows)];
    }

    public function invite(array $auth, int $campaignId, array $payload): array
    {
        $context = $this->guard->requireManage($auth, $campaignId);
        $validated = $this->validator->validate($payload);
        if (!$validated['valid']) {
            throw new CampaignException(
                'validation_failed', 'Invitation payload is invalid.', 422, $validated['errors']
            );
        }
        $target = $this->findTarget($validated['data']);
        $targetId = (int) $target['id'];
        if ($targetId === (int) $context['auth']['user_id']) {
            throw new CampaignException('cannot_invite_self', 'You cannot invite yourself.', 409);
        }
        $existing = $this->members
            ->where('campaign_id', $campaignId)
            ->where('user_id', $targetId)
            ->where('is_active', 1)
            ->first();
        if ($existing) {
            throw new CampaignException('already_member', 'User already belongs to this campaign.', 409);
        }
        $now = time();
        $data = [
            'campaign_id' => $campaignId,
            'invitee_user_id' => $targetId,
            'invited_by_user_id' => (int) $context['auth']['user_id'],
            'role' => $validated['data']['role'],
            'status' => 'pending',
            'pending_key' => $campaignId . ':' . $targetId,
            'message' => $validated['data']['message'] ?? null,
            'expires_at' => date('Y-m-d H:i:s', $now + 7 * 86400),
        ];
        try {
            if (!$this->invitations->insert($data)) {
                throw new CampaignException('invitation_write_failed', 'Invitation could not be created.', 500);
            }
        } catch (CampaignException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CampaignException('invitation_pending', 'A pending invitation already exists.', 409);
        }
        $row = $this->query()->where('campaign_invitations.id', $this->invitations->getInsertID())->first();
        return ['invitation' => CampaignInvitationPresenter::present($row)];
    }

    public function respond(array $auth, int $invitationId, bool $accept): array
    {
        $user = $this->authenticatedUser($auth);
        $invitation = $this->invitations
            ->where('id', $invitationId)
            ->where('invitee_user_id', (int) $user['id'])
            ->first();
        if (!$invitation) {
            throw new CampaignException('invitation_not_found', 'Invitation was not found.', 404);
        }
        if ($invitation['status'] !== 'pending') {
            throw new CampaignException('invitation_closed', 'Invitation is no longer pending.', 409);
        }
        if (strtotime((string) $invitation['expires_at']) <= time()) {
            $this->close($invitationId, 'expired');
            throw new CampaignException('invitation_expired', 'Invitation has expired.', 410);
        }
        $this->db->transBegin();
        try {
            if ($accept) {
                $this->memberService->activate(
                    (int) $invitation['campaign_id'],
                    (int) $user['id'],
                    (string) $invitation['role']
                );
            }
            $this->close($invitationId, $accept ? 'accepted' : 'rejected');
            if ($this->db->transStatus() === false) {
                throw new CampaignException('invitation_write_failed', 'Invitation could not be updated.', 500);
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['id' => $invitationId, 'status' => $accept ? 'accepted' : 'rejected'];
    }

    public function revoke(array $auth, int $campaignId, int $invitationId): array
    {
        $this->guard->requireManage($auth, $campaignId);
        $invitation = $this->invitations
            ->where('id', $invitationId)
            ->where('campaign_id', $campaignId)
            ->where('status', 'pending')
            ->first();
        if (!$invitation) {
            throw new CampaignException('invitation_not_found', 'Pending invitation was not found.', 404);
        }
        $this->close($invitationId, 'revoked');
        return ['id' => $invitationId, 'status' => 'revoked'];
    }

    private function query(): CampaignInvitationModel
    {
        return $this->invitations
            ->select('campaign_invitations.*, campaigns.name AS campaign_name')
            ->select('invitee.username AS invitee_username, invitee.email AS invitee_email')
            ->select('inviter.username AS inviter_username')
            ->join('campaigns', 'campaigns.id = campaign_invitations.campaign_id', 'inner')
            ->join('users invitee', 'invitee.id = campaign_invitations.invitee_user_id', 'inner')
            ->join('users inviter', 'inviter.id = campaign_invitations.invited_by_user_id', 'inner');
    }

    private function findTarget(array $data): array
    {
        $query = $this->users->where('deleted_at', null);
        if (isset($data['userId'])) {
            $query->where('id', $data['userId']);
        } else {
            $query->groupStart()->where('email', $data['identifier'])
                ->orWhere('username', $data['identifier'])->groupEnd();
        }
        $user = $query->first();
        if (!$user) {
            throw new CampaignException('user_not_found', 'User was not found.', 404);
        }
        return $user;
    }

    private function authenticatedUser(array $auth): array
    {
        $userId = (int) ($auth['user_id'] ?? 0);
        $user = $userId > 0
            ? $this->users->where('id', $userId)->where('deleted_at', null)->first()
            : null;
        if (!$user || !empty($auth['anonymous'])) {
            throw new CampaignException('unauthorized', 'Authentication is required.', 401);
        }
        return $user;
    }

    private function close(int $invitationId, string $status): void
    {
        $updated = $this->db->table('campaign_invitations')
            ->where('id', $invitationId)->where('status', 'pending')
            ->update([
                'status' => $status,
                'pending_key' => null,
                'responded_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        if (!$updated || $this->db->affectedRows() !== 1) {
            throw new CampaignException('invitation_conflict', 'Invitation changed concurrently.', 409);
        }
    }

    private function expirePending(): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->table('campaign_invitations')->where('status', 'pending')
            ->where('expires_at <', $now)->update([
                'status' => 'expired', 'pending_key' => null,
                'responded_at' => $now, 'updated_at' => $now,
            ]);
    }
}
