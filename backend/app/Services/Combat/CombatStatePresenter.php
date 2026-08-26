<?php

namespace App\Services\Combat;

final class CombatStatePresenter
{
    public static function present(?array $combat, array $rows, array $tokens, bool $canManage): array
    {
        $byId = [];
        foreach ($tokens as $token) $byId[(int) $token['id']] = $token;
        $combatants = [];
        foreach ($rows as $row) {
            $tokenId = (int) $row['token_id'];
            if (!isset($byId[$tokenId]) || (!$canManage && !empty($row['hidden']))) continue;
            $combatants[] = [
                'id' => (int) $row['id'],
                'tokenId' => $tokenId,
                'initiative' => (float) $row['initiative'],
                'sortOrder' => (int) $row['sort_order'],
                'hidden' => !empty($row['hidden']),
                'defeated' => !empty($row['defeated']),
                'token' => $byId[$tokenId],
            ];
        }
        $activeTokenId = null;
        if ($combat && !empty($combat['active']) && $rows) {
            $rawIndex = min(count($rows) - 1, max(0, (int) $combat['turn_index']));
            $candidate = (int) $rows[$rawIndex]['token_id'];
            if (isset($byId[$candidate]) && ($canManage || empty($rows[$rawIndex]['hidden']))) {
                $activeTokenId = $candidate;
            }
        }
        return [
            'combat' => [
                'id' => $combat ? (int) $combat['id'] : null,
                'sceneId' => $combat ? (int) $combat['scene_id'] : null,
                'active' => $combat && !empty($combat['active']),
                'round' => $combat ? (int) $combat['round'] : 0,
                'turnIndex' => $combat ? (int) $combat['turn_index'] : 0,
                'activeTokenId' => $activeTokenId,
                'combatants' => $combatants,
                'revision' => $combat ? (int) $combat['revision'] : 0,
            ],
            'capabilities' => ['canManage' => $canManage],
        ];
    }
}
