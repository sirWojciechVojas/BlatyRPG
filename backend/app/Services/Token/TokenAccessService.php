<?php

namespace App\Services\Token;

use App\Services\Character\CharacterDirectoryService;
use App\Services\Character\CharacterException;

final class TokenAccessService
{
    private $characters;
    private $cache = [];

    public function __construct(?CharacterDirectoryService $characters = null)
    {
        $this->characters = $characters ?: new CharacterDirectoryService();
    }

    public function canControl(array $auth, int $campaignId, array $token, bool $canManage): bool
    {
        if ($canManage) return true;
        $characterId = (int) ($token['character_id'] ?? 0);
        if ($characterId < 1) return false;
        $key = $campaignId . ':' . $characterId . ':' . (int) ($auth['user_id'] ?? 0);
        if (!array_key_exists($key, $this->cache)) {
            try {
                $this->characters->assertEditable($auth, $characterId, $campaignId);
                $this->cache[$key] = true;
            } catch (CharacterException $exception) {
                if (in_array($exception->status(), [401, 403, 404], true)) {
                    $this->cache[$key] = false;
                } else {
                    throw new TokenException(
                        $exception->errorCode(),
                        $exception->getMessage(),
                        $exception->status(),
                        $exception->details()
                    );
                }
            }
        }
        return $this->cache[$key];
    }

    public function assertCharacterInCampaign(int $campaignId, ?int $characterId): void
    {
        if ($characterId === null) return;
        $row = db_connect()->table('characters')->select('id')
            ->where('id', $characterId)->where('campaign_id', $campaignId)->get()->getRowArray();
        if (!$row) {
            throw new TokenException(
                'character_not_found',
                'Linked character was not found in this campaign.',
                422,
                ['characterId' => 'Choose a character from this campaign.']
            );
        }
    }
}
