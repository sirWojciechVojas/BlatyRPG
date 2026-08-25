<?php

namespace App\Services\Admin;

use App\Services\Character\CharacterCampaignGameGuard;
use App\Services\Character\CharacterException;
use CodeIgniter\Database\BaseConnection;

final class AdminCharacterAssignmentService
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function attachCampaign(int $characterId, int $campaignId, int $actorId): array
    {
        [$character, $campaign] = $this->rows($characterId, $campaignId);
        try {
            CharacterCampaignGameGuard::assertMatches($campaign, $character);
        } catch (CharacterException $exception) {
            throw new AdminException(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details()
            );
        }
        $key = ['character_id' => $characterId, 'campaign_id' => $campaignId];
        if (!$this->db->table('character_campaigns')->where($key)->countAllResults()) {
            $now = date('Y-m-d H:i:s');
            if (!$this->db->table('character_campaigns')->insert($key + [
                'assigned_by_user_id' => $actorId,
                'created_at' => $now,
                'updated_at' => $now,
            ])) {
                throw $this->writeFailure();
            }
        }
        if ((int) ($character['campaign_id'] ?? 0) < 1) {
            $this->updateCharacter($characterId, ['campaign_id' => $campaignId]);
        }
        return ['assigned' => true, 'characterId' => $characterId, 'campaignId' => $campaignId];
    }

    public function detachCampaign(int $characterId, int $campaignId): array
    {
        $this->rows($characterId, $campaignId);
        $key = ['character_id' => $characterId, 'campaign_id' => $campaignId];
        if (!$this->db->table('character_campaigns')->where($key)->countAllResults()) {
            throw new AdminException('assignment_not_found', 'Assignment was not found.', 404);
        }
        $this->db->transBegin();
        try {
            $this->db->table('resource_permissions')
                ->where('campaign_id', $campaignId)
                ->where('resource_type', 'character')
                ->where('resource_id', $characterId)->delete();
            $this->db->table('character_campaigns')->where($key)->delete();
            $this->syncLegacyCampaign($characterId);
            $this->syncLegacyOwner($characterId);
            $this->commit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['assigned' => false, 'characterId' => $characterId, 'campaignId' => $campaignId];
    }

    public function attachOwner(
        int $characterId,
        int $campaignId,
        int $userId,
        int $actorId
    ): array {
        $this->assertCampaignGameMaster($campaignId, $userId);
        $this->attachCampaign($characterId, $campaignId, $actorId);
        $key = [
            'campaign_id' => $campaignId,
            'resource_type' => 'character',
            'resource_id' => $characterId,
            'user_id' => $userId,
        ];
        $data = [
            'access_level' => 'owner',
            'granted_by_user_id' => $actorId,
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $table = $this->db->table('resource_permissions');
        $existing = $table->where($key)->get()->getRowArray();
        $ok = $existing
            ? $table->where('id', (int) $existing['id'])->update($data)
            : $table->insert($key + $data + ['created_at' => date('Y-m-d H:i:s')]);
        if (!$ok) {
            throw $this->writeFailure();
        }
        $character = $this->character($characterId);
        if ((int) ($character['user_id'] ?? 0) < 1) {
            $this->updateCharacter($characterId, ['user_id' => $userId]);
        }
        return [
            'assigned' => true, 'characterId' => $characterId,
            'campaignId' => $campaignId, 'userId' => $userId,
        ];
    }

    public function detachOwner(int $characterId, int $campaignId, int $userId): array
    {
        $this->rows($characterId, $campaignId);
        $key = [
            'campaign_id' => $campaignId,
            'resource_type' => 'character',
            'resource_id' => $characterId,
            'user_id' => $userId,
            'access_level' => 'owner',
        ];
        if (!$this->db->table('resource_permissions')->where($key)->delete()) {
            throw $this->writeFailure();
        }
        $this->syncLegacyOwner($characterId);
        return [
            'assigned' => false, 'characterId' => $characterId,
            'campaignId' => $campaignId, 'userId' => $userId,
        ];
    }

    private function rows(int $characterId, int $campaignId): array
    {
        return [$this->character($characterId), $this->campaign($campaignId)];
    }

    private function character(int $id): array
    {
        $row = $id > 0 ? $this->db->table('characters')->where('id', $id)
            ->get()->getRowArray() : null;
        if (!$row) {
            throw new AdminException('character_not_found', 'Character was not found.', 404);
        }
        return $row;
    }

    private function campaign(int $id): array
    {
        $row = $id > 0 ? $this->db->table('campaigns')->where('id', $id)
            ->where('deleted_at', null)->get()->getRowArray() : null;
        if (!$row) {
            throw new AdminException('campaign_not_found', 'Campaign was not found.', 404);
        }
        return $row;
    }

    private function assertCampaignGameMaster(int $campaignId, int $userId): void
    {
        $member = $this->db->table('campaign_members members')
            ->join('users users', 'users.id = members.user_id', 'inner')
            ->where('members.campaign_id', $campaignId)
            ->where('members.user_id', $userId)
            ->where('members.role', 'gm')->where('members.is_active', 1)
            ->where('users.deleted_at', null)->countAllResults();
        if (!$member) {
            throw new AdminException(
                'game_master_required',
                'The user is not an active GM at this Table.',
                422
            );
        }
    }

    private function syncLegacyCampaign(int $characterId): void
    {
        $row = $this->db->table('character_campaigns')->select('campaign_id')
            ->where('character_id', $characterId)->orderBy('id', 'ASC')
            ->get()->getRowArray();
        $this->updateCharacter($characterId, [
            'campaign_id' => $row ? (int) $row['campaign_id'] : null,
        ]);
    }

    private function syncLegacyOwner(int $characterId): void
    {
        $row = $this->db->table('resource_permissions')->select('user_id')
            ->where('resource_type', 'character')->where('resource_id', $characterId)
            ->where('access_level', 'owner')->orderBy('id', 'ASC')
            ->get()->getRowArray();
        $this->updateCharacter($characterId, [
            'user_id' => $row ? (int) $row['user_id'] : null,
        ]);
    }

    private function updateCharacter(int $characterId, array $data): void
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        if (!$this->db->table('characters')->where('id', $characterId)->update($data)) {
            throw $this->writeFailure();
        }
    }

    private function commit(): void
    {
        if ($this->db->transStatus() === false || !$this->db->transCommit()) {
            throw $this->writeFailure();
        }
    }

    private function writeFailure(): AdminException
    {
        return new AdminException('assignment_write_failed', 'Assignment could not be saved.', 500);
    }
}
