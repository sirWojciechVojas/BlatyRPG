<?php

namespace App\Services\Chat;

use CodeIgniter\Database\BaseConnection;

/** Serializes sends per user so concurrent workers cannot bypass limits. */
class CampaignChatRateLimiter
{
    private const BURST_LIMIT = 5;
    private const BURST_SECONDS = 10;
    private const MINUTE_LIMIT = 30;

    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    /** Must run inside the caller's transaction before counting and inserting. */
    public function lockSender(int $userId): void
    {
        $builder = $this->db->table('users')->select('id')->where('id', $userId);
        $sql = $builder->getCompiledSelect();
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $sql .= ' FOR UPDATE';
        }
        $this->db->query($sql)->getRowArray();
    }

    public function assertWithinLimit(int $campaignId, int $userId): void
    {
        $now = time();
        $burst = $this->countSince($campaignId, $userId, $now - self::BURST_SECONDS);
        $minute = $this->countSince($campaignId, $userId, $now - 60);
        if ($burst >= self::BURST_LIMIT || $minute >= self::MINUTE_LIMIT) {
            throw new CampaignChatException(
                'rate_limited',
                'Too many chat messages were sent.',
                429,
                ['retryAfter' => self::BURST_SECONDS]
            );
        }
    }

    private function countSince(int $campaignId, int $userId, int $timestamp): int
    {
        return $this->db->table('campaign_chat_messages')
            ->where('campaign_id', $campaignId)
            ->where('author_user_id', $userId)
            ->where('created_at >=', date('Y-m-d H:i:s', $timestamp))
            ->countAllResults();
    }
}
