<?php

namespace App\Services;

use App\Models\CharacterModel;
use App\Models\GameDefinitionModel;
use App\Models\RpgSystemModel;
use App\Libraries\GameStrategies\GameStrategyFactory;
use App\Services\Character\CharacterException;

class CharacterService
{
    protected $charModel;
    protected $defModel;
    protected $sysModel;
    protected $db;

    public function __construct()
    {
        $this->charModel = new CharacterModel();
        $this->defModel  = new GameDefinitionModel();
        $this->sysModel  = new RpgSystemModel();
        $this->db        = \Config\Database::connect();
    }

    /**
     * Główna metoda biznesowa zakupu elementu (umiejętności/zdolności).
     * @param int $charId ID Postaci
     * @param int $defId ID Definicji z game_definitions
     */
    public function purchaseDefinition(int $charId, int $defId): array
    {
        $character = $this->charModel->find($charId);
        $definition = $this->defModel->find($defId);

        if (!$character) {
            throw new CharacterException('character_not_found', 'Character was not found.', 404);
        }
        if (!$definition) {
            throw new CharacterException('definition_not_found', 'Definition was not found.', 404);
        }

        if ($character['system_id'] != $definition['system_id']) {
            throw new CharacterException(
                'definition_system_mismatch',
                'Definition belongs to a different game system.',
                409
            );
        }

        $system = $this->sysModel->find($character['system_id']);
        $sysCode = $system ? $system['code'] : 'unknown';
        $meta = $definition['metadata'] ?? [];
        $cost = isset($meta['koszt_xp']) ? (int) $meta['koszt_xp'] : 100;
        if ($cost < 0 || $cost > 1000000) {
            throw new CharacterException(
                'definition_cost_invalid',
                'Definition has an invalid experience cost.',
                409
            );
        }
        $charData = is_array($character['data'] ?? null) ? $character['data'] : [];
        $currentExp = 0;
        if (isset($charData['experience']['current'])) {
            $currentExp = (int) $charData['experience']['current'];
        } elseif (isset($charData['attributes']['exp']['current'])) {
            $currentExp = (int) $charData['attributes']['exp']['current'];
        }

        if ($currentExp < $cost) {
            throw new CharacterException(
                'insufficient_experience',
                'Character does not have enough experience.',
                402,
                ['required' => $cost, 'available' => $currentExp]
            );
        }

        try {
            $strategy = GameStrategyFactory::getStrategy($sysCode);
            $strategy->canPurchase($charData, $definition);
            $updates = $strategy->applyPurchase($charData, $definition);
        } catch (CharacterException $exception) {
            throw $exception;
        } catch (\Throwable $exception) {
            throw new CharacterException(
                'purchase_not_allowed',
                'This development purchase is not available.',
                409
            );
        }

        if (isset($charData['experience']['current'])) {
            $charData['experience']['current'] = $currentExp - $cost;
        } elseif (isset($charData['attributes']['exp']['current'])) {
            $charData['attributes']['exp']['current'] = $currentExp - $cost;
        } else {
            $charData['experience'] = ['current' => 0, 'total' => 0];
        }

        $revision = max(1, (int) ($character['revision'] ?? 1));
        $encoded = json_encode(
            $charData,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
        );
        if ($encoded === false) {
            throw new CharacterException(
                'character_write_failed',
                'Character development could not be saved.',
                500
            );
        }
        $written = $this->db->table('characters')
            ->set('data', $encoded)
            ->set('revision', 'revision + 1', false)
            ->set('updated_at', date('Y-m-d H:i:s'))
            ->where('id', $charId)
            ->where('revision', $revision)
            ->update();
        if (!$written) {
            throw new CharacterException(
                'character_write_failed',
                'Character development could not be saved.',
                500
            );
        }
        if ($this->db->affectedRows() !== 1) {
            $latest = $this->db->table('characters')
                ->select('revision')->where('id', $charId)->get()->getRowArray();
            throw new CharacterException(
                'character_conflict',
                'Character was changed by another user. Reload it before saving.',
                409,
                ['currentRevision' => max(1, (int) ($latest['revision'] ?? $revision))]
            );
        }

        return [
            'message' => 'Character development was purchased.',
            'definition' => ['id' => $defId, 'name' => (string) $definition['name']],
            'xpCost' => $cost,
            'remainingXp' => $currentExp - $cost,
            'updates' => $updates,
            'revision' => $revision + 1,
        ];
    }
}
