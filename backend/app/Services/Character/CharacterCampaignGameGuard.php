<?php

namespace App\Services\Character;

final class CharacterCampaignGameGuard
{
    public static function assertMatches(array $campaign, array $character): void
    {
        $systemId = (int) ($campaign['rpg_system_id'] ?? 0);
        $universeId = (int) ($campaign['rpg_universe_id'] ?? 0);
        if ($systemId < 1 || $universeId < 1) {
            throw new CharacterException(
                'campaign_game_required',
                'The campaign must have an RPG system and world.',
                422,
                ['campaignId' => 'Configure the campaign game first.']
            );
        }
        $errors = [];
        if ((int) ($character['system_id'] ?? 0) !== $systemId) {
            $errors['systemId'] = 'The character must use the campaign RPG system.';
        }
        if ((int) ($character['universe_id'] ?? 0) !== $universeId) {
            $errors['universeId'] = 'The character must use the campaign world.';
        }
        if ($errors) {
            throw new CharacterException(
                'campaign_game_mismatch',
                'The character game does not match the campaign.',
                422,
                $errors
            );
        }
    }
}
