<?php

namespace App\Services\Magic;

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use CodeIgniter\Database\BaseConnection;

/** Application service for character spellbooks, learning, casting and rituals. */
final class HeroMagicService
{
    private $db;
    private $guard;
    private $resolver;
    /** @var callable */
    private $d100;

    public function __construct(
        ?BaseConnection $db = null,
        ?CampaignGuardService $guard = null,
        ?Wfrp2MagicResolver $resolver = null,
        ?callable $d100 = null
    ) {
        $this->db = $db ?: \Config\Database::connect();
        $this->guard = $guard ?: new CampaignGuardService();
        $this->resolver = $resolver ?: new Wfrp2MagicResolver();
        $this->d100 = $d100 ?: static fn (): int => random_int(1, 100);
    }

    public function overview(int $campaignId, int $characterId, array $auth): array
    {
        $context = $this->context($campaignId, $characterId, $auth);
        $profile = $this->profile($campaignId, $characterId);
        $data = CharacterMagicData::decode($context['character']['data'] ?? []);
        $spells = $this->knownSpells($campaignId, $characterId);

        return [
            'character' => [
                'id' => $characterId,
                'name' => (string) $context['character']['name'],
                'magic' => CharacterMagicData::magic($data),
                'willpower' => CharacterMagicData::willpower($data),
                'experience' => CharacterMagicData::experience($data)['available'],
            ],
            'profile' => $this->presentProfile($profile),
            'knownSpells' => $spells,
            'learning' => [
                'traditions' => $this->traditions(),
                'revealedSpells' => $this->revealedSpells($campaignId, $characterId, $profile),
                'requests' => $this->learningRequests($campaignId, $characterId),
            ],
            'ingredients' => $this->inventory($campaignId, $characterId),
            'rituals' => $this->rituals($campaignId, $characterId),
            'history' => $this->historyItems($campaignId, $characterId, 100),
            'capabilities' => [
                'canCast' => CharacterMagicData::magic($data) > 0 && CharacterMagicData::canSpeak($data),
                'castBlockedReason' => $this->castBlockedReason($data),
                'canDecideLearning' => $context['isManager'],
                'canConfigureMagic' => $context['isManager'],
            ],
        ];
    }

    public function updatePreferences(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $this->context($campaignId, $characterId, $auth);
        $spellId = $this->positiveId($payload['spellId'] ?? null, 'spell_not_found');
        $this->assertKnown($campaignId, $characterId, $spellId);
        $allowed = ['spellId', 'favorite', 'pinned', 'pinOrder', 'note'];
        $this->rejectUnexpected($payload, $allowed);

        $favorite = !empty($payload['favorite']);
        $pinned = !empty($payload['pinned']);
        $note = trim((string) ($payload['note'] ?? ''));
        if (mb_strlen($note) > 1000) {
            throw $this->validation('Personal note is too long.');
        }
        if ($pinned) {
            $pinnedCount = $this->db->table('hero_spell_preferences')
                ->where(['campaign_id' => $campaignId, 'character_id' => $characterId, 'is_pinned' => 1])
                ->where('spell_id !=', $spellId)->countAllResults();
            if ($pinnedCount >= 6) {
                throw new CampaignException('pin_limit_reached', 'Up to six spells can be pinned to the HUD.', 409);
            }
        }
        $values = [
            'is_favorite' => $favorite ? 1 : 0,
            'is_pinned' => $pinned ? 1 : 0,
            'pin_order' => $pinned ? max(1, min(6, (int) ($payload['pinOrder'] ?? 6))) : null,
            'personal_note' => $note !== '' ? $note : null,
            'updated_at' => $this->now(),
        ];
        $existing = $this->db->table('hero_spell_preferences')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
        ])->get()->getRowArray();
        if ($existing) {
            $this->db->table('hero_spell_preferences')->where('id', $existing['id'])->update($values);
        } else {
            $this->db->table('hero_spell_preferences')->insert($values + [
                'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
            ]);
        }
        return ['spell' => $this->knownSpell($campaignId, $characterId, $spellId)];
    }

    public function revealSpell(
        int $campaignId,
        int $characterId,
        int $spellId,
        array $auth,
        array $payload
    ): array {
        $context = $this->context($campaignId, $characterId, $auth, true);
        $spell = $this->spell($spellId);
        $source = trim((string) ($payload['source'] ?? 'Formuła ujawniona przez MG'));
        if (mb_strlen($source) > 180) {
            throw $this->validation('Knowledge source is too long.');
        }
        $existing = $this->db->table('hero_spell_reveals')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
        ])->get()->getRowArray();
        if (!$existing) {
            $this->db->table('hero_spell_reveals')->insert([
                'campaign_id' => $campaignId, 'character_id' => $characterId,
                'spell_id' => $spellId, 'revealed_by_user_id' => $context['userId'],
                'source_label' => $source, 'revealed_at' => $this->now(),
            ]);
        }
        return ['spell' => $this->presentSpell($spell) + ['source' => $source]];
    }

    public function configureProfile(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $this->context($campaignId, $characterId, $auth, true);
        $chaosDice = filter_var($payload['chaosDice'] ?? null, FILTER_VALIDATE_INT);
        if ($chaosDice === false || $chaosDice < 0 || $chaosDice > 4) {
            throw $this->validation('Chaos dice count must be between 0 and 4.');
        }
        $existing = $this->profile($campaignId, $characterId);
        $values = ['chaos_dice' => $chaosDice, 'updated_at' => $this->now()];
        if ($existing) {
            $this->db->table('hero_magic_profiles')->where('id', $existing['id'])
                ->set('revision', 'revision + 1', false)->update($values);
        } else {
            $this->db->table('hero_magic_profiles')->insert($values + [
                'campaign_id' => $campaignId, 'character_id' => $characterId,
                'has_arcane_magic' => 0, 'revision' => 1, 'created_at' => $this->now(),
            ]);
        }
        return ['profile' => $this->presentProfile($this->profile($campaignId, $characterId))];
    }

    public function requestLearning(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $context = $this->context($campaignId, $characterId, $auth);
        $key = $this->idempotencyKey($payload['idempotencyKey'] ?? null);
        $existing = $this->db->table('spell_learning_requests')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'idempotency_key' => $key,
        ])->get()->getRowArray();
        if ($existing) {
            return ['request' => $this->presentLearningRequest($existing), 'duplicate' => true];
        }

        $type = strtolower(trim((string) ($payload['type'] ?? 'spell')));
        $spellId = null;
        $pathId = null;
        $cost = 0;
        if ($type === 'path') {
            $pathId = $this->positiveId($payload['pathId'] ?? null, 'magic_path_not_found');
            $path = $this->path($pathId);
            if (empty($path['verified_complete']) || (int) $path['spell_count'] !== 10) {
                throw new CampaignException('path_catalog_incomplete', 'This path is not ready for assignment.', 409);
            }
            if ($this->profile($campaignId, $characterId)['path_id'] ?? null) {
                throw new CampaignException('magic_path_already_selected', 'A magic path is already selected.', 409);
            }
        } elseif ($type === 'spell') {
            $spellId = $this->positiveId($payload['spellId'] ?? null, 'spell_not_found');
            $spell = $this->spell($spellId);
            if ($this->isKnown($campaignId, $characterId, $spellId)) {
                throw new CampaignException('spell_already_known', 'The character already knows this spell.', 409);
            }
            $revealed = $this->db->table('hero_spell_reveals')->where([
                'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
            ])->countAllResults() === 1;
            if (!$revealed) {
                throw new CampaignException('spell_not_revealed', 'This formula is not available to the character.', 404);
            }
            $profile = $this->profile($campaignId, $characterId);
            if (!$profile || empty($profile['has_arcane_magic']) || (int) $profile['tradition_id'] !== (int) $spell['tradition_id']) {
                throw new CampaignException('wrong_magic_tradition', 'Additional Spell must belong to the character tradition.', 409);
            }
            $cost = 100;
        } else {
            throw $this->validation('Choose a valid learning request type.');
        }

        $source = trim((string) ($payload['source'] ?? ''));
        $note = trim((string) ($payload['note'] ?? ''));
        $worldDate = trim((string) ($payload['worldDate'] ?? ''));
        if (mb_strlen($source) > 180 || mb_strlen($note) > 600 || mb_strlen($worldDate) > 80) {
            throw $this->validation('Learning request contains a value that is too long.');
        }
        $now = $this->now();
        $this->db->table('spell_learning_requests')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
            'request_type' => $type, 'spell_id' => $spellId, 'path_id' => $pathId,
            'xp_cost' => $cost, 'source_label' => $source ?: null,
            'player_note' => $note ?: null, 'world_date' => $worldDate ?: null,
            'status' => 'pending', 'idempotency_key' => $key,
            'requested_by_user_id' => $context['userId'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        $this->appendHistory($campaignId, $characterId, 'learning_requested', 'learning_request', $id, [
            'requestType' => $type, 'spellId' => $spellId, 'pathId' => $pathId,
            'xpCost' => $cost, 'message' => 'Zgłoszono naukę do zatwierdzenia przez MG.',
        ], $context['userId']);
        return [
            'request' => $this->presentLearningRequest(
                $this->db->table('spell_learning_requests')->where('id', $id)->get()->getRowArray()
            ),
            'experienceSpent' => 0,
            'duplicate' => false,
        ];
    }

    public function decideLearning(
        int $campaignId,
        int $requestId,
        array $auth,
        array $payload
    ): array {
        $decision = strtolower(trim((string) ($payload['decision'] ?? '')));
        if (!in_array($decision, ['approve', 'reject'], true)) {
            throw $this->validation('Choose approve or reject.');
        }
        $key = $this->idempotencyKey($payload['idempotencyKey'] ?? null);
        $publicNote = trim((string) ($payload['note'] ?? ''));
        $privateNote = trim((string) ($payload['gmPrivateNote'] ?? ''));
        if (mb_strlen($publicNote) > 600 || mb_strlen($privateNote) > 4000) {
            throw $this->validation('Decision note is too long.');
        }

        $this->db->transBegin();
        try {
            $request = $this->lockedRow(
                'SELECT * FROM spell_learning_requests WHERE campaign_id = ? AND id = ?',
                [$campaignId, $requestId]
            );
            if (!$request) {
                throw new CampaignException('learning_request_not_found', 'Learning request was not found.', 404);
            }
            $context = $this->context($campaignId, (int) $request['character_id'], $auth, true);
            if ($request['status'] !== 'pending') {
                $this->db->transCommit();
                return [
                    'request' => $this->presentLearningRequest($request),
                    'experienceSpent' => $this->expenseForRequest($requestId),
                    'duplicate' => true,
                ];
            }

            $spent = 0;
            $balance = null;
            if ($decision === 'approve') {
                if ($request['request_type'] === 'path') {
                    $this->approvePath($request, $context['userId']);
                } else {
                    [$spent, $balance] = $this->approveAdditionalSpell($request, $context['userId']);
                }
            }
            $status = $decision === 'approve' ? 'approved' : 'rejected';
            $now = $this->now();
            $this->db->table('spell_learning_requests')->where('id', $requestId)->update([
                'status' => $status, 'public_decision_note' => $publicNote ?: null,
                'gm_private_note' => $privateNote ?: null,
                'decided_by_user_id' => $context['userId'], 'decided_at' => $now,
                'updated_at' => $now,
            ]);
            $this->appendHistory(
                $campaignId,
                (int) $request['character_id'],
                $status === 'approved' ? 'learning_approved' : 'learning_rejected',
                'learning_request',
                $requestId,
                [
                    'requestType' => $request['request_type'],
                    'spellId' => $request['spell_id'] ? (int) $request['spell_id'] : null,
                    'pathId' => $request['path_id'] ? (int) $request['path_id'] : null,
                    'xpSpent' => $spent, 'experienceAfter' => $balance,
                    'note' => $publicNote,
                    'decisionKey' => $key,
                ],
                $context['userId'],
                $privateNote !== '' ? ['note' => $privateNote] : null
            );
            if (!$this->db->transStatus()) {
                throw new CampaignException('learning_write_failed', 'Learning decision could not be saved.', 500);
            }
            $this->db->transCommit();
            $updated = $this->db->table('spell_learning_requests')->where('id', $requestId)->get()->getRowArray();
            return [
                'request' => $this->presentLearningRequest($updated),
                'experienceSpent' => $spent,
                'experienceAfter' => $balance,
                'duplicate' => false,
            ];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function createCast(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $context = $this->context($campaignId, $characterId, $auth);
        $key = $this->idempotencyKey($payload['idempotencyKey'] ?? null);
        $existing = $this->db->table('spell_casts')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'idempotency_key' => $key,
        ])->get()->getRowArray();
        if ($existing) {
            return ['cast' => $this->presentCast($existing), 'duplicate' => true];
        }
        $spellId = $this->positiveId($payload['spellId'] ?? null, 'spell_not_found');
        $spell = $this->assertKnown($campaignId, $characterId, $spellId);
        $data = CharacterMagicData::decode($context['character']['data'] ?? []);
        $magic = CharacterMagicData::magic($data);
        if ($magic < 1) {
            throw new CampaignException('magic_zero', 'Mag 0: ta postać nie może rzucać czarów.', 409);
        }
        if (!CharacterMagicData::canSpeak($data)) {
            throw new CampaignException('casting_requires_speech', 'Postać nie może obecnie mówić.', 409);
        }
        $diceCount = filter_var($payload['powerDice'] ?? null, FILTER_VALIDATE_INT);
        if ($diceCount === false || $diceCount < 1 || $diceCount > $magic) {
            throw $this->validation("Choose between 1 and {$magic} power dice.");
        }
        $profile = $this->profile($campaignId, $characterId);
        $chaosDice = max(0, min(4, (int) ($profile['chaos_dice'] ?? 0)));
        $ingredient = $this->declaredIngredient($campaignId, $characterId, $spell, $payload['ingredient'] ?? null);
        $targets = $this->targets($payload['targets'] ?? []);
        $actionsRequired = $this->actionsRequired((string) $spell['casting_time']);
        $now = $this->now();
        $snapshot = $this->presentSpell($spell);
        $this->db->table('spell_casts')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
            'idempotency_key' => $key, 'status' => $actionsRequired > 1 ? 'casting' : 'declared',
            'power_dice_count' => $diceCount, 'chaos_dice_count' => $chaosDice,
            'actions_required' => $actionsRequired, 'actions_completed' => 1,
            'ingredient_kind' => $ingredient['kind'] ?? null,
            'ingredient_item_id' => $ingredient['id'] ?? null,
            'ingredient_name' => $ingredient['name'] ?? null,
            'ingredient_bonus' => $ingredient ? (int) $spell['ingredient_bonus'] : 0,
            'targets_json' => json_encode($targets, JSON_UNESCAPED_UNICODE),
            'channel_attempted' => 0, 'channel_succeeded' => 0, 'channel_bonus' => 0,
            'spell_snapshot_json' => json_encode($snapshot, JSON_UNESCAPED_UNICODE),
            'created_by_user_id' => $context['userId'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        return [
            'cast' => $this->presentCast($this->castRow($campaignId, $id, $characterId)),
            'duplicate' => false,
        ];
    }

    public function channel(int $campaignId, int $castId, array $auth): array
    {
        $this->db->transBegin();
        try {
            $cast = $this->lockedCast($campaignId, $castId);
            $context = $this->context($campaignId, (int) $cast['character_id'], $auth);
            if (!in_array($cast['status'], ['declared', 'casting'], true)) {
                throw new CampaignException('cast_not_open', 'This casting process is no longer open.', 409);
            }
            if (!empty($cast['channel_attempted'])) {
                $this->db->transCommit();
                return ['cast' => $this->presentCast($cast), 'duplicate' => true];
            }
            $data = CharacterMagicData::decode($context['character']['data'] ?? []);
            $roll = (int) ($this->d100)();
            $succeeded = $roll <= CharacterMagicData::willpower($data);
            $bonus = $succeeded ? CharacterMagicData::magic($data) : 0;
            $this->db->table('spell_casts')->where('id', $castId)->update([
                'channel_attempted' => 1, 'channel_succeeded' => $succeeded ? 1 : 0,
                'channel_roll' => $roll, 'channel_bonus' => $bonus, 'updated_at' => $this->now(),
            ]);
            $this->appendHistory($campaignId, (int) $cast['character_id'], 'channel_resolved', 'spell_cast', $castId, [
                'succeeded' => $succeeded, 'roll' => $roll, 'willpower' => CharacterMagicData::willpower($data),
                'bonus' => $bonus,
            ], $context['userId']);
            $this->db->transCommit();
            return ['cast' => $this->presentCast($this->castRow($campaignId, $castId)), 'duplicate' => false];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function advanceCast(int $campaignId, int $castId, array $auth): array
    {
        $this->db->transBegin();
        try {
            $cast = $this->lockedCast($campaignId, $castId);
            $context = $this->context($campaignId, (int) $cast['character_id'], $auth);
            if ($cast['status'] === 'declared') {
                $this->db->transCommit();
                return ['cast' => $this->presentCast($cast), 'duplicate' => true];
            }
            if ($cast['status'] !== 'casting') {
                throw new CampaignException('cast_not_open', 'This casting process cannot be advanced.', 409);
            }
            $character = $this->db->table('characters')->where('id', $cast['character_id'])->get()->getRowArray();
            $data = CharacterMagicData::decode($character['data'] ?? []);
            if (CharacterMagicData::magic($data) < 1 || !CharacterMagicData::canSpeak($data)) {
                throw new CampaignException('casting_interrupted', 'The character can no longer continue casting.', 409);
            }
            $completed = min((int) $cast['actions_required'], (int) $cast['actions_completed'] + 1);
            $status = $completed >= (int) $cast['actions_required'] ? 'declared' : 'casting';
            $this->db->table('spell_casts')->where('id', $castId)->update([
                'actions_completed' => $completed, 'status' => $status, 'updated_at' => $this->now(),
            ]);
            $this->appendHistory($campaignId, (int) $cast['character_id'], 'cast_advanced', 'spell_cast', $castId, [
                'actionsCompleted' => $completed, 'actionsRequired' => (int) $cast['actions_required'],
            ], $context['userId']);
            $this->db->transCommit();
            return ['cast' => $this->presentCast($this->castRow($campaignId, $castId)), 'duplicate' => false];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function resolveCast(int $campaignId, int $castId, array $auth): array
    {
        $this->db->transBegin();
        try {
            $cast = $this->lockedCast($campaignId, $castId);
            $context = $this->context($campaignId, (int) $cast['character_id'], $auth);
            if ($cast['status'] === 'resolved') {
                $this->db->transCommit();
                return ['cast' => $this->presentCast($cast), 'duplicate' => true];
            }
            if ($cast['status'] !== 'declared') {
                throw new CampaignException(
                    $cast['status'] === 'casting' ? 'cast_in_progress' : 'cast_not_open',
                    $cast['status'] === 'casting'
                        ? 'The required casting actions are not complete.'
                        : 'This casting process cannot be resolved.',
                    409
                );
            }
            $character = $this->lockedRow('SELECT * FROM characters WHERE id = ?', [$cast['character_id']]);
            $data = CharacterMagicData::decode($character['data'] ?? []);
            $magic = CharacterMagicData::magic($data);
            if ($magic < 1) {
                throw new CampaignException('magic_zero', 'Mag 0: ta postać nie może rzucać czarów.', 409);
            }
            if ((int) $cast['power_dice_count'] > $magic) {
                throw new CampaignException('dice_pool_changed', 'Current Mag no longer permits the declared dice pool.', 409);
            }
            if (!CharacterMagicData::canSpeak($data)) {
                throw new CampaignException('casting_requires_speech', 'Postać nie może obecnie mówić.', 409);
            }
            $spell = $this->assertKnown($campaignId, (int) $cast['character_id'], (int) $cast['spell_id']);
            $ingredient = null;
            if ($cast['ingredient_item_id']) {
                $ingredient = $this->consumeIngredient($campaignId, (int) $cast['character_id'], $spell, $cast);
            }
            $modifier = (int) $cast['ingredient_bonus'] + (int) $cast['channel_bonus'];
            $result = $this->resolver->resolve(
                (int) $cast['power_dice_count'],
                (int) $cast['chaos_dice_count'],
                (int) $spell['casting_number'],
                $modifier
            );
            $result['spell'] = ['id' => (int) $spell['id'], 'name' => (string) $spell['name_pl']];
            $result['ingredient'] = $ingredient;
            $result['channel'] = [
                'attempted' => !empty($cast['channel_attempted']),
                'succeeded' => !empty($cast['channel_succeeded']),
                'roll' => $cast['channel_roll'] ? (int) $cast['channel_roll'] : null,
                'bonus' => (int) $cast['channel_bonus'],
            ];
            $defense = trim((string) ($spell['defense_text'] ?? ''));
            $result['targetDefense'] = $defense !== ''
                ? ['status' => 'required', 'message' => $defense]
                : [
                    'status' => 'not_applicable',
                    'message' => 'Opis czaru nie przewiduje odrębnego testu obrony.',
                ];
            $result['effect'] = $this->effectSummary($spell, $magic);
            $now = $this->now();
            $this->db->table('spell_casts')->where('id', $castId)->update([
                'status' => 'resolved', 'result_json' => json_encode($result, JSON_UNESCAPED_UNICODE),
                'resolved_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($result['dice'] as $die) {
                $this->db->table('cast_dice')->insert([
                    'cast_id' => $castId, 'die_role' => $die['role'], 'die_order' => $die['order'],
                    'result' => $die['result'], 'included_in_power' => $die['includedInPower'] ? 1 : 0,
                    'included_in_curse' => $die['includedInCurse'] ? 1 : 0,
                    'included_in_automatic_failure' => $die['includedInAutomaticFailure'] ? 1 : 0,
                ]);
            }
            $this->appendHistory($campaignId, (int) $cast['character_id'], 'cast_resolved', 'spell_cast', $castId, $result, $context['userId']);
            if (!$this->db->transStatus()) {
                throw new CampaignException('cast_write_failed', 'Spell result could not be saved.', 500);
            }
            $this->db->transCommit();
            return ['cast' => $this->presentCast($this->castRow($campaignId, $castId)), 'duplicate' => false];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function cancelCast(int $campaignId, int $castId, array $auth): array
    {
        $cast = $this->castRow($campaignId, $castId);
        $context = $this->context($campaignId, (int) $cast['character_id'], $auth);
        if ($cast['status'] === 'resolved') {
            throw new CampaignException('cast_already_resolved', 'A resolved cast cannot be cancelled.', 409);
        }
        if ($cast['status'] !== 'cancelled') {
            $this->db->table('spell_casts')->where('id', $castId)->update([
                'status' => 'cancelled', 'cancelled_at' => $this->now(), 'updated_at' => $this->now(),
            ]);
            $this->appendHistory($campaignId, (int) $cast['character_id'], 'cast_cancelled', 'spell_cast', $castId, [
                'message' => 'Anulowano deklarację przed próbą; składnik nie został zużyty.',
            ], $context['userId']);
        }
        return ['cast' => $this->presentCast($this->castRow($campaignId, $castId))];
    }

    public function createRitual(
        int $campaignId,
        int $characterId,
        array $auth,
        array $payload
    ): array {
        $context = $this->context($campaignId, $characterId, $auth);
        $name = trim((string) ($payload['name'] ?? ''));
        $effect = trim((string) ($payload['effect'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 160 || mb_strlen($effect) < 10 || mb_strlen($effect) > 4000) {
            throw $this->validation('Provide a ritual name and intended effect.');
        }
        $requirements = $this->ritualList($payload['requirements'] ?? [], 'requirements');
        $ingredients = $this->ritualList($payload['ingredients'] ?? [], 'ingredients');
        $castingTime = trim((string) ($payload['castingTime'] ?? ''));
        $consequences = trim((string) ($payload['knownConsequences'] ?? ''));
        if (mb_strlen($castingTime) > 160 || mb_strlen($consequences) > 4000) {
            throw $this->validation('Ritual time or consequences are too long.');
        }
        $now = $this->now();
        $this->db->table('ritual_research')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
            'name' => $name, 'intended_effect' => $effect,
            'research_stage' => 'concept', 'status' => 'draft',
            'casting_time' => $castingTime ?: null,
            'requirements_json' => json_encode($requirements, JSON_UNESCAPED_UNICODE),
            'ingredients_json' => json_encode($ingredients, JSON_UNESCAPED_UNICODE),
            'known_consequences' => $consequences ?: null,
            'is_author_research' => 1, 'revision' => 1,
            'created_by_user_id' => $context['userId'], 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) $this->db->insertID();
        $this->appendHistory($campaignId, $characterId, 'ritual_concept_created', 'ritual_research', $id, [
            'name' => $name, 'stage' => 'concept', 'message' => 'Zapisano koncept rytuału; nie jest jeszcze poznaną Formułą.',
        ], $context['userId']);
        return ['ritual' => $this->presentRitual(
            $this->db->table('ritual_research')->where('id', $id)->get()->getRowArray()
        )];
    }

    private function ritualList($value, string $field): array
    {
        if (!is_array($value) || count($value) > 20) {
            throw $this->validation("Ritual {$field} must be a list of up to twenty entries.");
        }
        $items = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw $this->validation("Every ritual {$field} entry must be text.");
            }
            $item = trim($item);
            if ($item === '') {
                continue;
            }
            if (mb_strlen($item) > 240) {
                throw $this->validation("A ritual {$field} entry is too long.");
            }
            $items[] = $item;
        }
        return array_values(array_unique($items));
    }

    public function history(int $campaignId, int $characterId, array $auth, int $limit = 100): array
    {
        $this->context($campaignId, $characterId, $auth);
        return ['items' => $this->historyItems($campaignId, $characterId, max(1, min(200, $limit)))];
    }

    private function approvePath(array $request, int $userId): void
    {
        $campaignId = (int) $request['campaign_id'];
        $characterId = (int) $request['character_id'];
        $path = $this->path((int) $request['path_id']);
        if (empty($path['verified_complete']) || (int) $path['spell_count'] !== 10) {
            throw new CampaignException('path_catalog_incomplete', 'The selected path must contain exactly ten verified spells.', 409);
        }
        $profile = $this->profile($campaignId, $characterId);
        if ($profile && $profile['path_id'] && (int) $profile['path_id'] !== (int) $path['id']) {
            throw new CampaignException('magic_path_already_selected', 'A different magic path is already selected.', 409);
        }
        $values = [
            'tradition_id' => (int) $path['tradition_id'], 'path_id' => (int) $path['id'],
            'has_arcane_magic' => 1, 'updated_at' => $this->now(),
        ];
        if ($profile) {
            $this->db->table('hero_magic_profiles')->where('id', $profile['id'])
                ->set('revision', 'revision + 1', false)->update($values);
        } else {
            $this->db->table('hero_magic_profiles')->insert($values + [
                'campaign_id' => $campaignId, 'character_id' => $characterId,
                'chaos_dice' => 0, 'revision' => 1, 'created_at' => $this->now(),
            ]);
        }
        $rows = $this->db->table('hero_magic_path_spells')->where('path_id', $path['id'])
            ->orderBy('sort_order', 'ASC')->get()->getResultArray();
        foreach ($rows as $row) {
            if (!$this->isKnown($campaignId, $characterId, (int) $row['spell_id'])) {
                $this->db->table('hero_spell_grants')->insert([
                    'campaign_id' => $campaignId, 'character_id' => $characterId,
                    'spell_id' => (int) $row['spell_id'], 'grant_type' => 'path_package',
                    'source_reference' => 'path:' . $path['code'],
                    'granted_by_user_id' => $userId, 'granted_at' => $this->now(),
                    'verification_status' => 'verified',
                ]);
            }
        }
    }

    private function approveAdditionalSpell(array $request, int $userId): array
    {
        $campaignId = (int) $request['campaign_id'];
        $characterId = (int) $request['character_id'];
        $spellId = (int) $request['spell_id'];
        if ($this->isKnown($campaignId, $characterId, $spellId)) {
            throw new CampaignException('spell_already_known', 'The character already knows this spell.', 409);
        }
        $spell = $this->spell($spellId);
        $profile = $this->profile($campaignId, $characterId);
        if (!$profile || empty($profile['has_arcane_magic']) || (int) $profile['tradition_id'] !== (int) $spell['tradition_id']) {
            throw new CampaignException('wrong_magic_tradition', 'The spell does not belong to this character tradition.', 409);
        }
        $character = $this->lockedRow('SELECT * FROM characters WHERE id = ?', [$characterId]);
        $data = CharacterMagicData::decode($character['data'] ?? []);
        $cost = (int) $request['xp_cost'];
        try {
            $balance = CharacterMagicData::spendExperience($data, $cost);
        } catch (\InvalidArgumentException $exception) {
            throw new CampaignException('insufficient_experience', 'Character does not have enough experience.', 409, [
                'required' => $cost, 'available' => CharacterMagicData::experience($data)['available'],
            ]);
        }
        $this->db->table('characters')->where('id', $characterId)
            ->set('data', json_encode($data, JSON_UNESCAPED_UNICODE))
            ->set('revision', 'revision + 1', false)
            ->set('updated_at', $this->now())->update();
        $this->db->table('hero_spell_grants')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
            'spell_id' => $spellId, 'grant_type' => 'additional_spell',
            'source_reference' => (string) ($request['source_label'] ?? ''),
            'granted_by_user_id' => $userId, 'granted_at' => $this->now(),
            'verification_status' => 'verified',
        ]);
        $this->db->table('hero_experience_expenses')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
            'learning_request_id' => (int) $request['id'], 'amount' => $cost,
            'balance_after' => $balance, 'created_at' => $this->now(),
        ]);
        return [$cost, $balance];
    }

    private function context(int $campaignId, int $characterId, array $auth, bool $managerOnly = false): array
    {
        $campaignContext = $managerOnly
            ? $this->guard->requireManage($auth, $campaignId)
            : $this->guard->context($auth, $campaignId);
        $character = $this->db->table('characters')->where('id', $characterId)->get()->getRowArray();
        if (!$character || !$this->characterInCampaign($character, $campaignId)) {
            throw new CampaignException('character_not_found', 'Character was not found in this campaign.', 404);
        }
        $userId = (int) ($campaignContext['auth']['user_id'] ?? 0);
        $isManager = !empty($campaignContext['capabilities']['canManage']);
        $isOwner = (int) ($character['user_id'] ?? 0) === $userId;
        if (!$isOwner && $this->db->tableExists('resource_permissions')) {
            $isOwner = $this->db->table('resource_permissions')->where([
                'campaign_id' => $campaignId, 'resource_type' => 'character',
                'resource_id' => $characterId, 'user_id' => $userId, 'access_level' => 'owner',
            ])->countAllResults() > 0;
        }
        if (!$isManager && !$isOwner) {
            throw new CampaignException('forbidden', 'You cannot access this character spellbook.', 403);
        }
        $system = $this->db->table('rpg_systems')->select('code')->where('id', $character['system_id'])->get()->getRowArray();
        if (strtolower((string) ($system['code'] ?? '')) !== 'wfrp2ed') {
            throw new CampaignException('unsupported_magic_system', 'The spellbook is available only for WFRP 2e characters.', 409);
        }
        return ['character' => $character, 'campaign' => $campaignContext['campaign'], 'userId' => $userId, 'isManager' => $isManager];
    }

    private function characterInCampaign(array $character, int $campaignId): bool
    {
        if ((int) ($character['campaign_id'] ?? 0) === $campaignId) {
            return true;
        }
        return $this->db->tableExists('character_campaigns')
            && $this->db->table('character_campaigns')->where([
                'campaign_id' => $campaignId, 'character_id' => $character['id'],
            ])->countAllResults() > 0;
    }

    private function profile(int $campaignId, int $characterId): ?array
    {
        return $this->db->table('hero_magic_profiles profile')
            ->select('profile.*, tradition.name_pl AS tradition_name, tradition.wind_code, tradition.college_pl, tradition.accent_color, path.name_pl AS path_name')
            ->join('hero_magic_traditions tradition', 'tradition.id = profile.tradition_id', 'left')
            ->join('hero_magic_paths path', 'path.id = profile.path_id', 'left')
            ->where(['profile.campaign_id' => $campaignId, 'profile.character_id' => $characterId])
            ->get()->getRowArray() ?: null;
    }

    private function presentProfile(?array $profile): array
    {
        return [
            'id' => $profile ? (int) $profile['id'] : null,
            'traditionId' => $profile && $profile['tradition_id'] ? (int) $profile['tradition_id'] : null,
            'tradition' => (string) ($profile['tradition_name'] ?? ''),
            'wind' => (string) ($profile['wind_code'] ?? ''),
            'college' => (string) ($profile['college_pl'] ?? ''),
            'accentColor' => (string) ($profile['accent_color'] ?? '#8d4b32'),
            'pathId' => $profile && $profile['path_id'] ? (int) $profile['path_id'] : null,
            'path' => (string) ($profile['path_name'] ?? ''),
            'hasArcaneMagic' => !empty($profile['has_arcane_magic']),
            'chaosDice' => max(0, min(4, (int) ($profile['chaos_dice'] ?? 0))),
            'revision' => max(0, (int) ($profile['revision'] ?? 0)),
        ];
    }

    private function knownSpells(int $campaignId, int $characterId): array
    {
        $rows = $this->db->table('hero_spell_grants grant')
            ->select('spell.*, tradition.name_pl AS tradition_name, tradition.wind_code, preference.is_favorite, preference.is_pinned, preference.pin_order, preference.personal_note')
            ->join('hero_magic_spells spell', 'spell.id = grant.spell_id')
            ->join('hero_magic_traditions tradition', 'tradition.id = spell.tradition_id', 'left')
            ->join('hero_spell_preferences preference', 'preference.campaign_id = grant.campaign_id AND preference.character_id = grant.character_id AND preference.spell_id = grant.spell_id', 'left')
            ->where(['grant.campaign_id' => $campaignId, 'grant.character_id' => $characterId, 'spell.is_published' => 1])
            ->orderBy('spell.name_pl', 'ASC')->get()->getResultArray();
        return array_map(fn (array $row): array => $this->presentSpell($row), $rows);
    }

    private function knownSpell(int $campaignId, int $characterId, int $spellId): array
    {
        foreach ($this->knownSpells($campaignId, $characterId) as $spell) {
            if ($spell['id'] === $spellId) {
                return $spell;
            }
        }
        throw new CampaignException('spell_not_known', 'The character does not know this spell.', 403);
    }

    private function assertKnown(int $campaignId, int $characterId, int $spellId): array
    {
        if (!$this->isKnown($campaignId, $characterId, $spellId)) {
            throw new CampaignException('spell_not_known', 'The character does not know this spell.', 403);
        }
        return $this->spell($spellId);
    }

    private function isKnown(int $campaignId, int $characterId, int $spellId): bool
    {
        return $this->db->table('hero_spell_grants')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId, 'spell_id' => $spellId,
        ])->countAllResults() > 0;
    }

    private function spell(int $spellId): array
    {
        $row = $this->db->table('hero_magic_spells spell')
            ->select('spell.*, tradition.name_pl AS tradition_name, tradition.wind_code')
            ->join('hero_magic_traditions tradition', 'tradition.id = spell.tradition_id', 'left')
            ->where(['spell.id' => $spellId, 'spell.is_published' => 1])
            ->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('spell_not_found', 'Spell was not found.', 404);
        }
        return $row;
    }

    private function presentSpell(array $row): array
    {
        $special = json_decode((string) ($row['special_rules_json'] ?? ''), true);
        return [
            'id' => (int) $row['id'], 'code' => (string) $row['code'],
            'name' => (string) $row['name_pl'], 'magicType' => (string) $row['magic_type'],
            'traditionId' => $row['tradition_id'] ? (int) $row['tradition_id'] : null,
            'tradition' => (string) ($row['tradition_name'] ?? ''), 'wind' => (string) ($row['wind_code'] ?? ''),
            'castingNumber' => (int) $row['casting_number'], 'castingTime' => (string) $row['casting_time'],
            'range' => (string) ($row['range_text'] ?? ''), 'targetType' => (string) $row['target_type'],
            'duration' => (string) ($row['duration_text'] ?? ''),
            'ingredient' => $row['ingredient_name'] ? ['name' => (string) $row['ingredient_name'], 'bonus' => (int) $row['ingredient_bonus']] : null,
            'effect' => (string) $row['effect_summary'], 'defense' => (string) ($row['defense_text'] ?? ''),
            'specialRules' => is_array($special) ? $special : [],
            'source' => ['title' => (string) $row['source_title'], 'page' => $row['source_page'] ? (int) $row['source_page'] : null, 'version' => (string) $row['source_version']],
            'favorite' => !empty($row['is_favorite']), 'pinned' => !empty($row['is_pinned']),
            'pinOrder' => !empty($row['pin_order']) ? (int) $row['pin_order'] : null,
            'note' => (string) ($row['personal_note'] ?? ''),
        ];
    }

    private function traditions(): array
    {
        $traditions = $this->db->table('hero_magic_traditions')->where('system_code', 'wfrp2ed')
            ->orderBy('name_pl', 'ASC')->get()->getResultArray();
        return array_map(function (array $tradition): array {
            $paths = $this->db->table('hero_magic_paths path')
                ->select('path.*, COUNT(relation.spell_id) AS spell_count')
                ->join('hero_magic_path_spells relation', 'relation.path_id = path.id', 'left')
                ->where('path.tradition_id', $tradition['id'])->groupBy('path.id')
                ->orderBy('path.id', 'ASC')->get()->getResultArray();
            return [
                'id' => (int) $tradition['id'], 'code' => (string) $tradition['code'],
                'name' => (string) $tradition['name_pl'], 'wind' => (string) $tradition['wind_code'],
                'college' => (string) $tradition['college_pl'], 'accentColor' => (string) $tradition['accent_color'],
                'paths' => array_map(static fn (array $path): array => [
                    'id' => (int) $path['id'], 'code' => (string) $path['code'], 'name' => (string) $path['name_pl'],
                    'spellCount' => (int) $path['spell_count'],
                    'complete' => !empty($path['verified_complete']) && (int) $path['spell_count'] === 10,
                ], $paths),
            ];
        }, $traditions);
    }

    private function path(int $pathId): array
    {
        $row = $this->db->table('hero_magic_paths path')
            ->select('path.*, COUNT(relation.spell_id) AS spell_count')
            ->join('hero_magic_path_spells relation', 'relation.path_id = path.id', 'left')
            ->where('path.id', $pathId)->groupBy('path.id')->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('magic_path_not_found', 'Magic path was not found.', 404);
        }
        return $row;
    }

    private function revealedSpells(int $campaignId, int $characterId, ?array $profile): array
    {
        $knownIds = array_column($this->knownSpells($campaignId, $characterId), 'id');
        $rows = $this->db->table('hero_spell_reveals reveal')
            ->select('spell.*, tradition.name_pl AS tradition_name, tradition.wind_code, reveal.source_label')
            ->join('hero_magic_spells spell', 'spell.id = reveal.spell_id')
            ->join('hero_magic_traditions tradition', 'tradition.id = spell.tradition_id', 'left')
            ->where(['reveal.campaign_id' => $campaignId, 'reveal.character_id' => $characterId, 'spell.is_published' => 1])
            ->orderBy('spell.name_pl', 'ASC')->get()->getResultArray();
        return array_values(array_map(function (array $row) use ($profile): array {
            $spell = $this->presentSpell($row);
            $spell['sourceLabel'] = (string) ($row['source_label'] ?? '');
            $spell['eligible'] = $profile && !empty($profile['has_arcane_magic'])
                && (int) $profile['tradition_id'] === (int) $row['tradition_id'];
            $spell['xpCost'] = 100;
            return $spell;
        }, array_filter($rows, static fn (array $row): bool => !in_array((int) $row['id'], $knownIds, true))));
    }

    private function learningRequests(int $campaignId, int $characterId): array
    {
        $rows = $this->db->table('spell_learning_requests')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
        ])->orderBy('id', 'DESC')->limit(50)->get()->getResultArray();
        return array_map(fn (array $row): array => $this->presentLearningRequest($row), $rows);
    }

    private function presentLearningRequest(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'type' => (string) $row['request_type'],
            'spellId' => $row['spell_id'] ? (int) $row['spell_id'] : null,
            'pathId' => $row['path_id'] ? (int) $row['path_id'] : null,
            'xpCost' => (int) $row['xp_cost'], 'source' => (string) ($row['source_label'] ?? ''),
            'note' => (string) ($row['player_note'] ?? ''), 'worldDate' => (string) ($row['world_date'] ?? ''),
            'status' => (string) $row['status'], 'decisionNote' => (string) ($row['public_decision_note'] ?? ''),
            'createdAt' => $row['created_at'], 'decidedAt' => $row['decided_at'],
        ];
    }

    private function rituals(int $campaignId, int $characterId): array
    {
        return array_map(fn (array $row): array => $this->presentRitual($row),
            $this->db->table('ritual_research')->where(['campaign_id' => $campaignId, 'character_id' => $characterId])
                ->orderBy('updated_at', 'DESC')->get()->getResultArray());
    }

    private function presentRitual(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'name' => (string) $row['name'],
            'effect' => (string) $row['intended_effect'], 'stage' => (string) $row['research_stage'],
            'type' => (string) ($row['ritual_type'] ?? ''), 'language' => (string) ($row['language'] ?? ''),
            'minimumMagic' => $row['minimum_magic'] !== null ? (int) $row['minimum_magic'] : null,
            'learningCostXp' => $row['learning_cost_xp'] !== null ? (int) $row['learning_cost_xp'] : null,
            'castingNumber' => $row['casting_number'] !== null ? (int) $row['casting_number'] : null,
            'castingTime' => (string) ($row['casting_time'] ?? ''),
            'requirements' => $this->decodedList($row['requirements_json'] ?? null),
            'ingredients' => $this->decodedList($row['ingredients_json'] ?? null),
            'knownConsequences' => (string) ($row['known_consequences'] ?? ''),
            'status' => (string) $row['status'], 'revision' => (int) $row['revision'],
        ];
    }

    private function inventory(int $campaignId, int $characterId): array
    {
        if (!$this->db->tableExists('shop_containers')) {
            return [];
        }
        $container = $this->db->table('shop_containers')->where([
            'campaign_id' => $campaignId, 'container_type' => 'CHARACTER',
            'owner_code' => 'CHAR_' . $characterId, 'is_active' => 1,
        ])->get()->getRowArray();
        if (!$container) {
            return [];
        }
        $items = [];
        foreach ($this->db->table('shop_container_template_items row')
            ->select('row.id, row.quantity, template.name')
            ->join('shop_templates template', 'template.id = row.template_id')
            ->where(['row.campaign_id' => $campaignId, 'row.container_id' => $container['id']])
            ->where('row.quantity >', 0)->where('template.deleted_at', null)
            ->get()->getResultArray() as $row) {
            $items[] = ['kind' => 'template_stack', 'id' => (int) $row['id'], 'name' => (string) $row['name'], 'quantity' => (int) $row['quantity']];
        }
        foreach ($this->db->table('shop_container_instance_items row')
            ->select('instance.id, COALESCE(instance.name_override, template.name) AS name')
            ->join('shop_item_instances instance', 'instance.id = row.instance_id')
            ->join('shop_templates template', 'template.id = instance.template_id')
            ->where(['row.campaign_id' => $campaignId, 'row.container_id' => $container['id']])
            ->where('template.deleted_at', null)->get()->getResultArray() as $row) {
            $items[] = ['kind' => 'instance', 'id' => (int) $row['id'], 'name' => (string) $row['name'], 'quantity' => 1];
        }
        return $items;
    }

    private function declaredIngredient(int $campaignId, int $characterId, array $spell, $payload): ?array
    {
        if ($payload === null || $payload === false || $payload === []) {
            return null;
        }
        if (empty($spell['ingredient_name']) || !is_array($payload)) {
            throw $this->validation('This spell has no matching optional ingredient.');
        }
        $kind = (string) ($payload['kind'] ?? '');
        $id = $this->positiveId($payload['id'] ?? null, 'ingredient_not_found');
        foreach ($this->inventory($campaignId, $characterId) as $item) {
            if ($item['kind'] === $kind && $item['id'] === $id && $this->sameIngredient($item['name'], (string) $spell['ingredient_name'])) {
                return $item;
            }
        }
        throw new CampaignException('ingredient_not_accessible', 'The matching ingredient is not in the character inventory.', 409);
    }

    private function consumeIngredient(int $campaignId, int $characterId, array $spell, array $cast): array
    {
        $item = $this->declaredIngredient($campaignId, $characterId, $spell, [
            'kind' => $cast['ingredient_kind'], 'id' => $cast['ingredient_item_id'],
        ]);
        if ($item['kind'] === 'template_stack') {
            $this->db->table('shop_container_template_items')->set('quantity', 'quantity - 1', false)
                ->where('id', $item['id'])->where('quantity >=', 1)->update();
            if ($this->db->affectedRows() !== 1) {
                throw new CampaignException('ingredient_unavailable', 'The ingredient is no longer available.', 409);
            }
            $this->db->table('shop_container_template_items')->where('id', $item['id'])->where('quantity <=', 0)->delete();
        } else {
            $this->db->table('shop_container_instance_items')->where('instance_id', $item['id'])->delete();
            if ($this->db->affectedRows() !== 1) {
                throw new CampaignException('ingredient_unavailable', 'The ingredient is no longer available.', 409);
            }
            $this->db->table('shop_item_instances')->where('id', $item['id'])->delete();
        }
        return ['name' => $item['name'], 'bonus' => (int) $spell['ingredient_bonus'], 'consumed' => true];
    }

    private function sameIngredient(string $actual, string $expected): bool
    {
        $normalize = static function (string $value): string {
            $value = mb_strtolower(trim($value));
            $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            return preg_replace('/[^a-z0-9]+/', '', strtolower($transliterated !== false ? $transliterated : $value)) ?: '';
        };
        $actual = $normalize($actual);
        $expected = $normalize($expected);
        return $actual !== '' && $expected !== '' && ($actual === $expected || str_contains($actual, $expected) || str_contains($expected, $actual));
    }

    private function effectSummary(array $spell, int $magic): array
    {
        $special = json_decode((string) ($spell['special_rules_json'] ?? ''), true) ?: [];
        if (($spell['code'] ?? '') === 'fireball') {
            return [
                'status' => 'ready_for_combat', 'missiles' => $magic, 'strength' => 3,
                'message' => "{$magic} pociski o Sile 3; przydział celów i obrażenia rozstrzyga moduł walki.",
            ];
        }
        return ['status' => 'requires_resolution', 'rules' => $special, 'message' => (string) $spell['effect_summary']];
    }

    private function castBlockedReason(array $data): string
    {
        if (CharacterMagicData::magic($data) < 1) {
            return 'Mag 0: ta postać nie może rzucać czarów.';
        }
        if (!CharacterMagicData::canSpeak($data)) {
            return 'Postać nie może obecnie mówić, a rzucanie wymaga mowy.';
        }
        return '';
    }

    private function actionsRequired(string $castingTime): int
    {
        $normalized = mb_strtolower(trim($castingTime));
        if (preg_match('/^(\d+)\s+(?:akcj|actions?)/u', $normalized, $matches)) {
            return max(1, min(20, (int) $matches[1]));
        }
        return 1;
    }

    private function targets($value): array
    {
        if (!is_array($value) || count($value) > 12) {
            throw $this->validation('Choose up to twelve valid targets.');
        }
        $targets = [];
        foreach ($value as $target) {
            if (is_string($target)) {
                $targets[] = ['label' => mb_substr(trim($target), 0, 160)];
                continue;
            }
            if (is_array($target)) {
                $targets[] = [
                    'type' => mb_substr((string) ($target['type'] ?? 'descriptive'), 0, 32),
                    'id' => isset($target['id']) ? mb_substr((string) $target['id'], 0, 80) : null,
                    'label' => mb_substr(trim((string) ($target['label'] ?? '')), 0, 160),
                    'missiles' => max(0, min(10, (int) ($target['missiles'] ?? 0))),
                ];
            }
        }
        return $targets;
    }

    private function castRow(int $campaignId, int $castId, ?int $characterId = null): array
    {
        $builder = $this->db->table('spell_casts')->where(['campaign_id' => $campaignId, 'id' => $castId]);
        if ($characterId !== null) {
            $builder->where('character_id', $characterId);
        }
        $row = $builder->get()->getRowArray();
        if (!$row) {
            throw new CampaignException('cast_not_found', 'Casting process was not found.', 404);
        }
        return $row;
    }

    private function lockedCast(int $campaignId, int $castId): array
    {
        $row = $this->lockedRow(
            'SELECT * FROM spell_casts WHERE campaign_id = ? AND id = ?',
            [$campaignId, $castId]
        );
        if (!$row) {
            throw new CampaignException('cast_not_found', 'Casting process was not found.', 404);
        }
        return $row;
    }

    /** SQLite serializes test writes and does not implement SELECT ... FOR UPDATE. */
    private function lockedRow(string $sql, array $bindings): ?array
    {
        if (strtolower((string) $this->db->DBDriver) !== 'sqlite3') {
            $sql .= ' FOR UPDATE';
        }
        return $this->db->query($sql, $bindings)->getRowArray() ?: null;
    }

    private function presentCast(array $row): array
    {
        return [
            'id' => (int) $row['id'], 'characterId' => (int) $row['character_id'],
            'spellId' => (int) $row['spell_id'], 'status' => (string) $row['status'],
            'powerDiceCount' => (int) $row['power_dice_count'], 'chaosDiceCount' => (int) $row['chaos_dice_count'],
            'actionsRequired' => (int) ($row['actions_required'] ?? 1),
            'actionsCompleted' => (int) ($row['actions_completed'] ?? 1),
            'ingredient' => $row['ingredient_item_id'] ? [
                'kind' => (string) $row['ingredient_kind'], 'id' => (int) $row['ingredient_item_id'],
                'name' => (string) $row['ingredient_name'], 'bonus' => (int) $row['ingredient_bonus'],
            ] : null,
            'targets' => $this->decodedList($row['targets_json'] ?? null),
            'channel' => [
                'attempted' => !empty($row['channel_attempted']), 'succeeded' => !empty($row['channel_succeeded']),
                'roll' => $row['channel_roll'] ? (int) $row['channel_roll'] : null, 'bonus' => (int) $row['channel_bonus'],
            ],
            'spell' => $this->decodedObject($row['spell_snapshot_json'] ?? null),
            'result' => $this->decodedObject($row['result_json'] ?? null),
            'createdAt' => $row['created_at'], 'resolvedAt' => $row['resolved_at'],
        ];
    }

    private function historyItems(int $campaignId, int $characterId, int $limit): array
    {
        $rows = $this->db->table('hero_magic_history')->where([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
        ])->orderBy('id', 'DESC')->limit($limit)->get()->getResultArray();
        return array_map(fn (array $row): array => [
            'id' => (int) $row['id'], 'type' => (string) $row['event_type'],
            'referenceType' => (string) ($row['reference_type'] ?? ''),
            'referenceId' => $row['reference_id'] ? (int) $row['reference_id'] : null,
            'data' => $this->decodedObject($row['public_payload_json'] ?? null),
            'createdAt' => $row['created_at'],
        ], $rows);
    }

    private function appendHistory(
        int $campaignId,
        int $characterId,
        string $type,
        ?string $referenceType,
        ?int $referenceId,
        array $public,
        ?int $userId,
        ?array $gm = null
    ): void {
        $this->db->table('hero_magic_history')->insert([
            'campaign_id' => $campaignId, 'character_id' => $characterId,
            'event_type' => $type, 'reference_type' => $referenceType, 'reference_id' => $referenceId,
            'public_payload_json' => json_encode($public, JSON_UNESCAPED_UNICODE),
            'gm_payload_json' => $gm ? json_encode($gm, JSON_UNESCAPED_UNICODE) : null,
            'created_by_user_id' => $userId, 'created_at' => $this->now(),
        ]);
    }

    private function expenseForRequest(int $requestId): int
    {
        $row = $this->db->table('hero_experience_expenses')->select('amount')
            ->where('learning_request_id', $requestId)->get()->getRowArray();
        return (int) ($row['amount'] ?? 0);
    }

    private function decodedObject($value): array
    {
        if (is_array($value)) {
            return $value;
        }
        $decoded = json_decode((string) $value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decodedList($value): array
    {
        return array_values($this->decodedObject($value));
    }

    private function rejectUnexpected(array $payload, array $allowed): void
    {
        $unexpected = array_diff(array_keys($payload), $allowed);
        if ($unexpected) {
            throw new CampaignException('validation_failed', 'Request contains unsupported fields.', 422, array_fill_keys($unexpected, 'This field is not accepted.'));
        }
    }

    private function idempotencyKey($value): string
    {
        $key = trim((string) $value);
        if (!preg_match('/^[A-Za-z0-9._:-]{8,128}$/', $key)) {
            throw $this->validation('A valid idempotency key is required.');
        }
        return $key;
    }

    private function positiveId($value, string $code): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            throw new CampaignException($code, 'Resource was not found.', 404);
        }
        return (int) $id;
    }

    private function validation(string $message): CampaignException
    {
        return new CampaignException('validation_failed', $message, 422);
    }

    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }
}
