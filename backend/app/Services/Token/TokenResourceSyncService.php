<?php

namespace App\Services\Token;

use CodeIgniter\Database\BaseConnection;

final class TokenResourceSyncService
{
    private $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?: \Config\Database::connect();
    }

    public function fromToken(
        int $campaignId,
        ?int $characterId,
        array $previous,
        array $resources
    ): array {
        if (!TokenResourceValidator::hasBindings($resources)) return $resources;
        if (!$characterId) {
            throw new TokenException(
                'character_required',
                'A token must be linked to a character before binding resources.',
                422
            );
        }
        $character = $this->db->table('characters')->where('id', $characterId)
            ->where('campaign_id', $campaignId)->get()->getRowArray();
        if (!$character) {
            throw new TokenException('character_not_found', 'Character was not found.', 404);
        }
        $data = $this->decode($character['data'] ?? null);
        $sync = TokenResourceBinding::tokenToActor($previous, $resources, $data);
        if ($sync['characterChanged']) {
            $this->writeCharacter($character, $sync['characterData']);
        }
        return $sync['resources'];
    }

    public function fromCharacter(int $characterId, array $characterData): void
    {
        $tokens = $this->db->table('scene_tokens')->where('character_id', $characterId)
            ->where('deleted_at', null)->get()->getResultArray();
        foreach ($tokens as $token) {
            $resources = TokenMovementResource::fromMovement(
                $this->decode($token['bars_json'] ?? null),
                (float) ($token['movement_range'] ?? 6),
                (float) ($token['movement_spent'] ?? 0)
            );
            $sync = TokenResourceBinding::actorToToken($resources, $characterData);
            $movement = TokenMovementResource::fromResources(
                $sync['resources'],
                (float) ($token['movement_range'] ?? 6),
                (float) ($token['movement_spent'] ?? 0)
            );
            $movementChanged = $movement['sourceIndex'] !== null && (
                $movement['range'] !== (float) ($token['movement_range'] ?? 6)
                || $movement['spent'] !== (float) ($token['movement_spent'] ?? 0)
            );
            if (!$sync['changed'] && !$movementChanged) continue;
            $encoded = json_encode($movement['resources'], JSON_UNESCAPED_UNICODE);
            $builder = $this->db->table('scene_tokens')->set('bars_json', $encoded);
            if ($movement['sourceIndex'] !== null) {
                $builder->set('movement_range', $movement['range'])
                    ->set('movement_spent', $movement['spent']);
            }
            $ok = $builder
                ->set('revision', 'revision + 1', false)
                ->set('updated_at', date('Y-m-d H:i:s'))
                ->where('id', (int) $token['id'])
                ->where('revision', (int) $token['revision'])->update();
            if (!$ok || $this->db->affectedRows() !== 1) {
                throw new \RuntimeException('Token resources could not be synchronized.');
            }
        }
    }

    private function writeCharacter(array $character, array $data): void
    {
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            throw new TokenException('validation_failed', 'Character data is invalid.', 422);
        }
        $ok = $this->db->table('characters')->set('data', $encoded)
            ->set('revision', 'revision + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', (int) $character['id'])
            ->where('revision', (int) $character['revision'])->update();
        if (!$ok || $this->db->affectedRows() !== 1) {
            throw new TokenException(
                'character_conflict',
                'Character changed while token resources were being saved.',
                409
            );
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
