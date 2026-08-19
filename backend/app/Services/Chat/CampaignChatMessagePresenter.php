<?php

namespace App\Services\Chat;

class CampaignChatMessagePresenter
{
    public function present(array $row): array
    {
        $authorId = isset($row['author_user_id']) ? (int) $row['author_user_id'] : null;
        return [
            'id' => (int) $row['id'],
            'revision' => (int) $row['id'],
            'campaignId' => (int) $row['campaign_id'],
            'type' => (string) ($row['message_type'] ?? 'text'),
            'body' => (string) $row['body'],
            'author' => [
                'id' => $authorId,
                'name' => (string) ($row['author_name'] ?? 'Unknown user'),
            ],
            'clientNonce' => $row['client_nonce'] ?? null,
            'metadata' => $row['metadata_json'] ?? null,
            'createdAt' => $row['created_at'] ?? null,
        ];
    }
}
