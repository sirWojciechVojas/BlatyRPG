<?php

namespace App\Services\Token;

use CodeIgniter\Database\BaseConnection;

final class TokenResourceMovementService
{
    private $sync;
    private $active = false;
    private $resourceTouched = false;
    private $previous = [];
    private $working = [];

    public function __construct(BaseConnection $db)
    {
        $this->sync = new TokenResourceSyncService($db);
    }

    public function touches(array $data): bool
    {
        return (bool) array_intersect(
            ['bars_json', 'x', 'y', 'movement_range', 'movement_spent'],
            array_keys($data)
        );
    }

    public function prepare(
        int $campaignId,
        array $token,
        array $data,
        bool $canManage
    ): array {
        if (!$this->touches($data)) return $data;
        $this->active = true;
        $this->resourceTouched = array_key_exists('bars_json', $data);
        $this->previous = TokenMovementResource::fromMovement(
            TokenResourceValidator::stored($token['bars_json'] ?? []),
            (float) ($token['movement_range'] ?? 6),
            (float) ($token['movement_spent'] ?? 0)
        );
        $this->working = $this->previous;
        if (!$this->resourceTouched) return $data;
        if (!$canManage && TokenMovementResource::controlsChanged(
            $this->previous,
            $data['bars_json'],
            (float) ($token['movement_range'] ?? 6),
            (float) ($token['movement_spent'] ?? 0)
        )) {
            throw new TokenException(
                'forbidden', 'Only a game master can change movement resources.', 403
            );
        }
        $input = TokenMovementResource::applyBubbleInputs(
            $this->previous,
            $data['bars_json']
        );
        $this->working = $this->sync->fromToken(
            $campaignId,
            $this->characterId($token, $data),
            array_key_exists('character_id', $data) ? [] : $this->previous,
            $input
        );
        $state = TokenMovementResource::fromResources(
            $this->working,
            (float) ($data['movement_range'] ?? $token['movement_range'] ?? 6),
            (float) ($data['movement_spent'] ?? $token['movement_spent'] ?? 0)
        );
        $this->working = $state['resources'];
        $data['bars_json'] = $this->working;
        if ($state['sourceIndex'] !== null) {
            $data['movement_range'] = $state['range'];
            $data['movement_spent'] = $state['spent'];
        }
        return $data;
    }

    public function finish(int $campaignId, array $token, array $data): array
    {
        if (!$this->active) return $data;
        $final = TokenMovementResource::fromMovement(
            $this->working,
            (float) ($data['movement_range'] ?? $token['movement_range'] ?? 6),
            (float) ($data['movement_spent'] ?? $token['movement_spent'] ?? 0)
        );
        if ($final !== $this->working) {
            $final = $this->sync->fromToken(
                $campaignId,
                $this->characterId($token, $data),
                $this->working,
                $final
            );
        }
        if ($this->resourceTouched || $final !== $this->previous) {
            $data['bars_json'] = $final;
        }
        return $data;
    }

    private function characterId(array $token, array $data): ?int
    {
        $value = array_key_exists('character_id', $data)
            ? $data['character_id'] : ($token['character_id'] ?? null);
        return $value === null ? null : (int) $value;
    }
}
