<?php

namespace App\Services\Authorization;

use App\Services\Campaign\CampaignException;
use CodeIgniter\Database\BaseConnection;

/** Assigns character access without mutating the existing shop ownership domain. */
class CharacterOwnerAssigner
{
    private $db;
    private $writer;

    public function __construct(
        BaseConnection $db,
        ResourcePermissionWriter $writer
    ) {
        $this->db = $db;
        $this->writer = $writer;
    }

    public function assign(
        int $campaignId,
        int $characterId,
        int $userId,
        bool $primary,
        int $grantorId
    ): array {
        $this->db->transBegin();
        try {
            if ($primary) {
                $updated = $this->db->table('characters')
                    ->where('id', $characterId)
                    ->where('campaign_id', $campaignId)
                    ->update([
                        'user_id' => $userId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                if (!$updated) {
                    throw $this->writeFailure();
                }
            }
            $result = $this->writer->store(
                $campaignId,
                ResourceType::CHARACTER,
                $characterId,
                $userId,
                AccessLevel::OWNER,
                $grantorId
            );
            if ($this->db->transStatus() === false || !$this->db->transCommit()) {
                throw $this->writeFailure();
            }
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return $result + ['primary' => $primary];
    }

    private function writeFailure(): CampaignException
    {
        return new CampaignException(
            'character_write_failed',
            'Owner could not be assigned.',
            500
        );
    }
}
