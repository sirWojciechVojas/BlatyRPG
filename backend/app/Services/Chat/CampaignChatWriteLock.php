<?php

namespace App\Services\Chat;

use CodeIgniter\Database\BaseConnection;

/** Serializes committed message revisions within one campaign. */
final class CampaignChatWriteLock
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    /** Must be acquired before the per-sender rate-limit lock. */
    public function lockCampaign(int $campaignId): void
    {
        $builder = $this->db->table('campaigns')->select('id')->where('id', $campaignId);
        $sql = $builder->getCompiledSelect();
        if (strtolower((string) $this->db->DBDriver) === 'mysqli') {
            $sql .= ' FOR UPDATE';
        }
        $this->db->query($sql)->getRowArray();
    }
}
