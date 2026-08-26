<?php

namespace App\Services\Token;

use CodeIgniter\Database\BaseConnection;

final class TokenGroupMovementService
{
    private $db;
    private $tokens;

    public function __construct(
        ?BaseConnection $db = null,
        ?SceneTokenService $tokens = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->tokens = $tokens ?: new SceneTokenService($this->db);
    }

    public function move(
        int $campaignId,
        int $sceneId,
        array $auth,
        array $rawMoves
    ): array {
        $moves = TokenGroupMovementPayload::validate($rawMoves);
        $items = [];
        $this->db->transBegin();
        try {
            foreach ($moves as $move) {
                $items[] = $this->tokens->update(
                    $campaignId,
                    $sceneId,
                    $move['tokenId'],
                    $auth,
                    [
                        'revision' => $move['revision'],
                        'x' => $move['x'],
                        'y' => $move['y'],
                    ],
                    $move['waypoints']
                );
            }
            if (!$this->db->transStatus()) {
                throw new TokenException(
                    'token_group_move_failed',
                    'The token group could not be moved.',
                    500
                );
            }
            $this->db->transCommit();
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
        return ['items' => $items];
    }
}
