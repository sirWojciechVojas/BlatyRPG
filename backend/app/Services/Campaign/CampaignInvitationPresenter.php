<?php

namespace App\Services\Campaign;

final class CampaignInvitationPresenter
{
    public static function present(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'campaignName' => (string) ($row['campaign_name'] ?? ''),
            'invitee' => [
                'id' => (int) $row['invitee_user_id'],
                'username' => (string) ($row['invitee_username'] ?? ''),
                'email' => (string) ($row['invitee_email'] ?? ''),
            ],
            'invitedBy' => [
                'id' => (int) $row['invited_by_user_id'],
                'username' => (string) ($row['inviter_username'] ?? ''),
            ],
            'role' => (string) $row['role'],
            'status' => (string) $row['status'],
            'message' => $row['message'] ?? null,
            'expiresAt' => $row['expires_at'] ?? null,
            'respondedAt' => $row['responded_at'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ];
    }
}
