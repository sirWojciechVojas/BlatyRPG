<?php

namespace App\Services\Token;

use CodeIgniter\Database\BaseConnection;

final class TokenMovementResetService
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function resetForAdvance(int $campaignId, string $event, array $tokenIds = []): int
    {
        if (!in_array($event, ['turn', 'round'], true)) return 0;
        $builder = $this->db->table('scene_tokens')
            ->where('campaign_id', $campaignId)
            ->where('movement_reset_mode', $event)
            ->where('movement_spent >', 0)
            ->where('deleted_at', null);
        $ids = array_values(array_unique(array_filter(array_map('intval', $tokenIds))));
        if ($ids) $builder->whereIn('id', $ids);
        $tokens = $builder->get()->getResultArray();
        $updated = 0;
        $this->db->transBegin();
        try {
            $sync = new TokenResourceSyncService($this->db);
            foreach ($tokens as $token) {
                $previous = TokenMovementResource::fromMovement(
                    $this->decode($token['bars_json'] ?? null),
                    (float) ($token['movement_range'] ?? 6),
                    (float) ($token['movement_spent'] ?? 0)
                );
                $resources = TokenMovementResource::fromMovement(
                    $previous,
                    (float) ($token['movement_range'] ?? 6),
                    0
                );
                if ($resources !== $previous) {
                    $resources = $sync->fromToken(
                        $campaignId,
                        isset($token['character_id']) ? (int) $token['character_id'] : null,
                        $previous,
                        $resources
                    );
                }
                $ok = $this->db->table('scene_tokens')
                    ->set('movement_spent', 0)
                    ->set('bars_json', json_encode($resources, JSON_UNESCAPED_UNICODE))
                    ->set('revision', 'revision + 1', false)
                    ->set('updated_at', date('Y-m-d H:i:s'))
                    ->where('id', (int) $token['id'])
                    ->where('revision', (int) $token['revision'])->update();
                if (!$ok || $this->db->affectedRows() !== 1) {
                    throw new \RuntimeException('Token movement could not be reset.');
                }
                $updated++;
            }
            $this->db->transCommit();
            return $updated;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    private function decode($value): array
    {
        if (is_array($value)) return $value;
        if (!is_string($value) || $value === '') return [];
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }
}
