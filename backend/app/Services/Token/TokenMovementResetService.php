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
        $builder->set('movement_spent', 0)
            ->set('revision', 'revision + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->update();
        return $this->db->affectedRows();
    }
}
