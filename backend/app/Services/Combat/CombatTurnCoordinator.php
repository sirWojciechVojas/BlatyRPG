<?php

namespace App\Services\Combat;

use App\Services\Token\TokenMovementResetService;
use CodeIgniter\Database\BaseConnection;

final class CombatTurnCoordinator
{
    private $db;
    private $movement;

    public function __construct(BaseConnection $db, TokenMovementResetService $movement)
    {
        $this->db = $db;
        $this->movement = $movement;
    }

    public function advance(
        int $campaignId,
        array $combat,
        array $rows,
        int $direction,
        callable $write
    ): bool {
        $next = CombatTurnSequence::advance(
            (int) $combat['round'],
            (int) $combat['turn_index'],
            count($rows),
            $direction
        );
        $this->db->transBegin();
        try {
            $write($combat, [
                'round' => $next['round'], 'turn_index' => $next['turnIndex'],
            ]);
            $changed = 0;
            if ($direction > 0 && $next['newRound']) {
                $changed += $this->movement->resetForAdvance(
                    $campaignId, 'round', array_column($rows, 'token_id')
                );
            }
            if ($direction > 0) {
                $changed += $this->movement->resetForAdvance(
                    $campaignId, 'turn', [(int) $rows[$next['turnIndex']]['token_id']]
                );
            }
            if ($this->db->transStatus() === false) {
                throw new CombatException('combat_write_failed', 'Combat could not be advanced.', 500);
            }
            $this->db->transCommit();
            return $changed > 0;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
