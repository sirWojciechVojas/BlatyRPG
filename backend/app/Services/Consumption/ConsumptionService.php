<?php

namespace App\Services\Consumption;

use CodeIgniter\Database\BaseConnection;

/** Executes consumption entirely on the server in one idempotent transaction. */
final class ConsumptionService
{
    private $db;
    private $catalog;
    private $roll;

    public function __construct(?BaseConnection $db = null, ?ConsumptionCatalog $catalog = null, ?callable $roll = null)
    {
        $this->db = $db ?: \Config\Database::connect();
        $this->catalog = $catalog ?: new ConsumptionCatalog();
        $this->roll = $roll ?: static function (): int { return random_int(1, 100); };
    }

    public function consume(int $campaignId, string $ownerCode, array $payload, string $requestKey): array
    {
        $kind = strtolower((string) ($payload['itemKind'] ?? ''));
        $itemId = $this->positiveId($payload['itemId'] ?? null, 'item_not_found');
        if (!in_array($kind, ['template_stack', 'instance'], true)) {
            throw new ConsumptionException('invalid_consumption_item', 'Nieprawidłowy przedmiot.', 400);
        }
        if (!preg_match('/^[A-Za-z0-9_-]{16,128}$/', $requestKey)) {
            throw new ConsumptionException('invalid_request_key', 'Brak poprawnego klucza żądania.', 400);
        }

        $this->db->transBegin();
        try {
            $previous = $this->requestResult($campaignId, $requestKey);
            if ($previous !== null) {
                $this->db->transCommit();
                return $previous;
            }
            $this->db->query(
                'INSERT IGNORE INTO consumption_requests (campaign_id, request_key, created_at, updated_at) VALUES (?, ?, NOW(), NOW())',
                [$campaignId, $requestKey]
            );
            $previous = $this->requestResult($campaignId, $requestKey);
            if ($previous !== null) {
                $this->db->transCommit();
                return $previous;
            }

            $item = $this->ownedItem($campaignId, strtoupper($ownerCode), $kind, $itemId);
            $resolved = $this->catalog->resolve($item['template'], $item['instance']);
            $profile = $resolved['profile'];
            if (!$profile) throw new ConsumptionException('not_consumable', 'Ten przedmiot nie jest przeznaczony do spożycia.');

            $state = $this->lockedWorldState($campaignId);
            $worldMinute = (int) $state['world_minute'];
            $character = $this->characterForOwner($campaignId, strtoupper($ownerCode));
            if (!$character) throw new ConsumptionException('character_not_found', 'Nie znaleziono postaci.', 404);
            $data = $this->decodeData($character['data'] ?? []);
            $portions = $kind === 'template_stack'
                ? max(0, (int) $item['row']['quantity'])
                : max(0, (int) ($item['instance']['consumption_portions'] ?? 1));
            $availability = $this->availability($campaignId, (int) $character['id'], $data, $worldMinute);
            $view = $this->catalog->playerView(
                $profile,
                (string) ($item['instance']['consumption_identification'] ?? 'unknown'),
                $portions,
                $availability
            );
            if (!empty($view['disabled'])) {
                throw new ConsumptionException('consumption_unavailable', (string) $view['disabledReason']);
            }
            if (!empty($view['requiresConfirmation']) && empty($payload['confirmed'])) {
                throw new ConsumptionException('consumption_confirmation_required', 'To spożycie wymaga potwierdzenia.', 409);
            }

            $events = [];
            $newMinute = $worldMinute + max(1, (int) $profile['consumeMinutes']);
            $this->consumeOnePortion($item, $kind);
            $data = $this->expireEffects($data, $newMinute, $events);
            $this->applyNutrients($data, $profile, $events);
            $effect = $this->catalog->effect($profile['effectId']);
            $this->applyKnownEffect($data, $profile, $effect ?: [], $newMinute, $events);
            $this->applyHiddenRisk($data, $profile, $newMinute, $events);
            $this->resolveDueStates($data, $newMinute, $events);
            $this->appendJournal($data, $events, $newMinute);

            $this->saveCharacter($character, $data);
            $this->db->table('campaign_consumption_states')->where('campaign_id', $campaignId)
                ->update(['world_minute' => $newMinute, 'updated_at' => date('Y-m-d H:i:s')]);
            $remaining = max(0, $portions - 1);
            $result = [
                'ok' => true,
                'itemKind' => $kind,
                'itemId' => $itemId,
                'remainingPortions' => $remaining,
                'worldMinute' => $newMinute,
                'events' => $events,
                'character' => $this->characterPayload($character, $data),
                'consumption' => $this->catalog->playerView(
                    $profile,
                    (string) ($item['instance']['consumption_identification'] ?? 'unknown'),
                    $remaining,
                    $availability
                ),
            ];
            $this->db->table('consumption_requests')->where(['campaign_id' => $campaignId, 'request_key' => $requestKey])
                ->update(['result_json' => json_encode($result, JSON_UNESCAPED_UNICODE), 'updated_at' => date('Y-m-d H:i:s')]);
            if (!$this->db->transStatus()) throw new ConsumptionException('consumption_save_failed', 'Nie udało się zapisać spożycia.', 500);
            $this->db->transCommit();
            return $result;
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    public function advanceWorldTime(int $campaignId, int $minutes): array
    {
        if ($minutes < 1 || $minutes > 43200) throw new ConsumptionException('invalid_world_time', 'Czas musi mieścić się w zakresie 1–43200 minut.', 422);
        $this->db->transBegin();
        try {
            $state = $this->lockedWorldState($campaignId);
            $minute = (int) $state['world_minute'] + $minutes;
            $affected = 0;
            foreach ($this->db->table('characters')->where('campaign_id', $campaignId)->get()->getResultArray() as $character) {
                $data = $this->decodeData($character['data'] ?? []);
                $before = $data;
                $events = [];
                $data = $this->expireEffects($data, $minute, $events);
                $this->resolveDueStates($data, $minute, $events);
                if ($events || $data !== $before) {
                    $this->appendJournal($data, $events, $minute);
                    $this->saveCharacter($character, $data);
                    $affected++;
                }
            }
            $this->db->table('campaign_consumption_states')->where('campaign_id', $campaignId)
                ->update(['world_minute' => $minute, 'updated_at' => date('Y-m-d H:i:s')]);
            if (!$this->db->transStatus()) throw new ConsumptionException('world_time_save_failed', 'Nie udało się przesunąć czasu świata.', 500);
            $this->db->transCommit();
            return ['ok' => true, 'worldMinute' => $minute, 'affectedCharacters' => $affected];
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }

    /**
     * Adapter for the existing game-rule callers: applies temporary consumption
     * modifiers and removes a one-test effect once its matching test is made.
     */
    public function testCharacter(array &$characterData, string $trait, int $modifier, string $testKind): array
    {
        return $this->test($characterData, $trait, $modifier, $testKind);
    }

    private function ownedItem(int $campaignId, string $ownerCode, string $kind, int $itemId): array
    {
        $container = $this->db->table('shop_containers')->where([
            'campaign_id' => $campaignId, 'container_type' => 'CHARACTER', 'owner_code' => $ownerCode, 'is_active' => 1,
        ])->get()->getRowArray();
        if (!$container) throw new ConsumptionException('item_not_accessible', 'Przedmiot nie jest w dostępnym ekwipunku.', 403);
        if ($kind === 'template_stack') {
            $row = $this->db->table('shop_container_template_items')->where([
                'id' => $itemId, 'campaign_id' => $campaignId, 'container_id' => $container['id'],
            ])->get()->getRowArray();
            $template = $row ? $this->db->table('shop_templates')->where(['id' => $row['template_id'], 'campaign_id' => $campaignId, 'deleted_at' => null])->get()->getRowArray() : null;
            if (!$row || !$template) throw new ConsumptionException('item_not_accessible', 'Przedmiot nie jest w dostępnym ekwipunku.', 403);
            return compact('row', 'template') + ['instance' => null];
        }
        $row = $this->db->table('shop_container_instance_items')->where([
            'campaign_id' => $campaignId, 'container_id' => $container['id'], 'instance_id' => $itemId,
        ])->get()->getRowArray();
        $instance = $row ? $this->db->table('shop_item_instances')->where(['id' => $itemId, 'campaign_id' => $campaignId])->get()->getRowArray() : null;
        $template = $instance ? $this->db->table('shop_templates')->where(['id' => $instance['template_id'], 'campaign_id' => $campaignId, 'deleted_at' => null])->get()->getRowArray() : null;
        if (!$row || !$instance || !$template) throw new ConsumptionException('item_not_accessible', 'Przedmiot nie jest w dostępnym ekwipunku.', 403);
        return compact('row', 'instance', 'template');
    }

    private function consumeOnePortion(array $item, string $kind): void
    {
        if ($kind === 'template_stack') {
            $this->db->table('shop_container_template_items')->set('quantity', 'quantity - 1', false)
                ->where('id', $item['row']['id'])->where('quantity >=', 1)->update();
            if ($this->db->affectedRows() !== 1) throw new ConsumptionException('portion_unavailable', 'Brak porcji.', 409);
            $this->db->table('shop_container_template_items')->where('id', $item['row']['id'])->where('quantity <=', 0)->delete();
            return;
        }
        $portions = max(1, (int) ($item['instance']['consumption_portions'] ?? 1));
        if ($portions === 1) {
            $this->db->table('shop_container_instance_items')->where('id', $item['row']['id'])->delete();
            $this->db->table('shop_item_instances')->where('id', $item['instance']['id'])->delete();
            return;
        }
        $this->db->table('shop_item_instances')->set('consumption_portions', 'consumption_portions - 1', false)
            ->where('id', $item['instance']['id'])->where('consumption_portions >=', 1)->update();
        if ($this->db->affectedRows() !== 1) throw new ConsumptionException('portion_unavailable', 'Brak porcji.', 409);
    }

    private function availability(int $campaignId, int $characterId, array $data, int $minute): array
    {
        $statuses = array_map('strtolower', array_merge((array) ($data['statuses'] ?? []), (array) ($data['conditions'] ?? [])));
        $busyUntil = (int) (($data['consumption']['actionUntilMinute'] ?? 0));
        return [
            'accessible' => true,
            'busy' => $busyUntil > $minute,
            'incapacitated' => !empty($data['unconscious']) || in_array('unconscious', $statuses, true) || in_array('nieprzytomny', $statuses, true),
            'inCombat' => $this->inActiveCombat($campaignId, $characterId),
        ];
    }

    private function applyNutrients(array &$data, array $profile, array &$events): void
    {
        $consumption =& $this->consumptionData($data);
        $consumption['satietyHours'] = min(24, max(0, (float) ($consumption['satietyHours'] ?? 0) + (float) $profile['satietyHours']));
        $consumption['hydrationHours'] = min(24, max(0, (float) ($consumption['hydrationHours'] ?? 0) + (float) $profile['hydrationHours']));
        $events[] = 'Spożyto porcję produktu.';
    }

    private function applyKnownEffect(array &$data, array $profile, array $effect, int $minute, array &$events): void
    {
        $id = (string) ($profile['effectId'] ?? '');
        if (in_array($id, ['E00', 'E24', 'E26', 'E27', 'E28', 'E29', 'E30', 'E31', 'E35'], true)) return;
        if (in_array($id, ['E12', 'E22', 'E25'], true)) {
            if ((int) ($data['consumption']['restMinutes'] ?? 0) >= 60 && $this->healLightlyWounded($data, 1)) $events[] = 'Czujesz się pokrzepiony.';
            if ($id === 'E25') $this->addEffect($data, 'feast', 5, $minute + 360, 'next_test:sw,ogd');
            return;
        }
        if ($id === 'E33') {
            if ($this->healLightlyWounded($data, 4)) $events[] = 'Rany zaczynają się zasklepiać.';
            return;
        }
        if ($id === 'E14') {
            $effects =& $this->consumptionData($data)['effects'];
            if (isset($effects['hangover'])) {
                $effects['hangover']['expiresAtMinute'] = max($minute, (int) $effects['hangover']['expiresAtMinute'] - 60);
                $events[] = 'Czujesz, że kac słabnie.';
            }
            return;
        }
        if ($id === 'E32') {
            $this->addEffect($data, 'mad_hats', 1, $minute + $this->dice(2, 10), 'mad_hats');
            return;
        }
        if ($id === 'E18') {
            $this->addEffect($data, 'alcohol_courage', 5, $minute + 60, 'next_test:sw');
            $this->addEffect($data, 'alcohol_dexterity', -5, $minute + 60, 'persistent:zr');
            return;
        }
        if ($id === 'E19') {
            $this->addEffect($data, 'alcohol_social', 5, $minute + 60, 'next_test:plotkowanie,ogd');
            return;
        }
        if ($id === 'E34') {
            $this->addEffect($data, 'bugman_fear', 99, $minute + $this->dice(1, 10) * 60, 'immunity:strach');
            return;
        }
        $minutes = max(60, (int) ($profile['effectWindowMinutes'] ?? 0));
        $stackKey = in_array($id, ['E02', 'E21'], true) ? 'warming' : $id;
        $bonus = $id === 'E21' ? 10 : 5;
        $testKinds = [
            'E01' => 'odporność', 'E02' => 'odporność', 'E03' => 'k', 'E04' => 'sw', 'E05' => 'spostrzegawczość,przeszukiwanie',
            'E06' => 'plotkowanie,przekonywanie', 'E07' => 'odporność', 'E08' => 'odporność', 'E09' => 'zr', 'E10' => 'sztuka przetrwania',
            'E11' => 'nawigacja,żeglarstwo,wiosłowanie', 'E13' => 'sw', 'E15' => 'int', 'E16' => 'odporność', 'E17' => 'odporność', 'E21' => 'odporność',
        ];
        $this->addEffect($data, $stackKey, $bonus, $minute + $minutes, 'next_test:' . ($testKinds[$id] ?? ''));
        $events[] = 'Czujesz działanie spożytego produktu.';
    }

    private function applyHiddenRisk(array &$data, array $profile, int $minute, array &$events): void
    {
        $id = (string) ($profile['effectId'] ?? '');
        $negative = (string) ($profile['negativeTest'] ?? '');
        if ($id === 'E35' || $negative === '—' || $negative === 'Bez testu') return;
        if (!empty($profile['alcohol'])) {
            $this->applyAlcohol($data, $id, $negative, $minute, $events);
            return;
        }
        if (mb_stripos($negative, 'przedawkowaniu') !== false) {
            $doseKey = 'dose:' . (string) $profile['id'];
            $consumption =& $this->consumptionData($data);
            if (!isset($consumption['doses']) || !is_array($consumption['doses'])) $consumption['doses'] = [];
            $history =& $consumption['doses'];
            $history[$doseKey] = ((array) ($history[$doseKey] ?? []));
            $history[$doseKey][] = $minute;
            $history[$doseKey] = array_values(array_filter($history[$doseKey], static fn ($value): bool => $value >= $minute - 240));
            if (count($history[$doseKey]) < 2) return;
        }
        if (mb_stripos($negative, 'odp') === false) return;
        $modifier = $this->testModifier($negative);
        $test = $this->test($data, 'odp', $modifier, 'odporność');
        if ($test['success']) return;
        $this->applyFailure($data, $id, $minute, $test['degrees'], $events);
    }

    private function applyAlcohol(array &$data, string $effectId, string $negative, int $minute, array &$events): void
    {
        $consumption =& $this->consumptionData($data);
        $day = intdiv($minute, 1440);
        if ((int) ($consumption['alcoholDay'] ?? -1) !== $day) {
            $consumption['alcoholDay'] = $day;
            $consumption['alcoholDrinks'] = 0;
        }
        $units = $effectId === 'E34' ? 4 : ($effectId === 'E20' ? 2 : 1);
        $consumption['alcoholDrinks'] = (int) ($consumption['alcoholDrinks'] ?? 0) + $units;
        $threshold = max(1, $this->trait($data, 'wt'));
        if ($effectId !== 'E34' && $consumption['alcoholDrinks'] <= $threshold) return;
        $test = $this->test($data, 'odp', $this->testModifier($negative), 'mocna głowa');
        if ($test['success']) return;
        $penalty = -5 * min(4, $test['degrees']);
        $this->addEffect($data, 'alcohol', $penalty, $minute + 60, 'alcohol');
        $events[] = 'Świat lekko wiruje.';
    }

    private function applyFailure(array &$data, string $effectId, int $minute, int $degrees, array &$events): void
    {
        $duration = $this->dice(1, 10) * 60;
        if ($effectId === 'E26') {
            $this->addEffect($data, 'food_poisoning', -5, $minute + $duration, 'poison');
            if ($degrees >= 2) $this->damage($data, 1, $events);
            $events[] = 'Twój żołądek gwałtownie protestuje.';
        } elseif ($effectId === 'E27') {
            $this->addEffect($data, 'food_poisoning', -10, $minute + $duration, 'poison');
            $this->damage($data, 1, $events);
            $events[] = 'Twój żołądek gwałtownie protestuje.';
        } elseif ($effectId === 'E28') {
            $this->addEffect($data, 'hallucination', -10, $minute + $duration, 'poison');
            $events[] = 'Otoczenie zaczyna niepokojąco falować.';
        } elseif ($effectId === 'E29') {
            $this->addEffect($data, 'sleep', -10, $minute + $duration, 'poison');
            if (!isset($data['conditions']) || !is_array($data['conditions'])) $data['conditions'] = [];
            $data['conditions'][] = 'unconscious';
            $events[] = 'Ogarnia cię obezwładniająca senność.';
        } elseif ($effectId === 'E30') {
            $this->damage($data, 4, $events);
            $this->addEffect($data, 'strong_poison', -10, $minute + $duration, 'poison');
            $events[] = 'Przez ciało przebiega piekący ból.';
        } elseif ($effectId === 'E31') {
            $this->consumptionData($data)['fatalAtMinute'] = $minute + $this->dice(2, 10);
            $events[] = 'Potrawa pozostawiła dziwnie gorzki posmak.';
        } else {
            $this->addEffect($data, 'nausea', -5, $minute + 60, 'nausea');
            $events[] = 'Twój żołądek protestuje.';
        }
    }

    private function resolveDueStates(array &$data, int $minute, array &$events): void
    {
        $fatalAt = (int) ($data['consumption']['fatalAtMinute'] ?? 0);
        if ($fatalAt > 0 && $fatalAt <= $minute) {
            unset($data['consumption']['fatalAtMinute']);
            if ($this->spendFate($data)) {
                $this->setTrait($data, 'zyw', 1);
                $events[] = 'Punkt Przeznaczenia ocalił ci życie.';
            } else {
                $data['dead'] = true;
                $events[] = 'Trucizna okazuje się śmiertelna.';
            }
        }
    }

    private function addEffect(array &$data, string $key, int $value, int $expiresAt, string $kind): void
    {
        $effects =& $this->consumptionData($data)['effects'];
        $current = (array) ($effects[$key] ?? []);
        $effects[$key] = ['key' => $key, 'value' => $current ? max((int) $current['value'], $value) : $value, 'expiresAtMinute' => max((int) ($current['expiresAtMinute'] ?? 0), $expiresAt), 'kind' => $kind];
    }

    private function expireEffects(array $data, int $minute, array &$events = []): array
    {
        if (empty($data['consumption']['effects']) || !is_array($data['consumption']['effects'])) return $data;
        $active = [];
        foreach ($data['consumption']['effects'] as $key => $effect) {
            if ((int) ($effect['expiresAtMinute'] ?? 0) > $minute) {
                $active[$key] = $effect;
                continue;
            }
            if (($effect['key'] ?? $key) === 'mad_hats') {
                $this->damage($data, 2, $events);
                $events[] = 'Gdy działanie używki ustaje, ogarnia cię gwałtowne osłabienie.';
            }
            if (($effect['key'] ?? $key) === 'E15') {
                $active['fatigue_resistance'] = [
                    'key' => 'fatigue_resistance', 'value' => -5,
                    'expiresAtMinute' => $minute + 1440, 'kind' => 'next_test:odporność',
                ];
                $events[] = 'Gdy pobudzenie mija, czujesz zmęczenie.';
            }
        }
        $data['consumption']['effects'] = $active;
        return $data;
    }

    private function test(array &$data, string $trait, int $modifier, string $kind): array
    {
        $target = max(1, min(99, $this->trait($data, $trait) + $modifier + $this->activeModifier($data, $kind)));
        if ($kind === 'mocna głowa' && $this->hasTalent($data, 'mocna głowa')) $target = min(99, $target + 10);
        if ($kind === 'odporność' && $this->hasTalent($data, 'odporność na trucizny')) $target = min(99, $target + 10);
        $roll = max(1, min(100, (int) call_user_func($this->roll)));
        return ['roll' => $roll, 'target' => $target, 'success' => $roll <= $target, 'degrees' => max(1, (int) ceil(max(0, $roll - $target) / 10))];
    }

    private function activeModifier(array &$data, string $kind): int
    {
        $modifier = 0;
        foreach ((array) ($data['consumption']['effects'] ?? []) as $key => $effect) {
            if (($effect['kind'] ?? '') === 'alcohol' && in_array($kind, ['mocna głowa', 'odporność'], true)) $modifier += (int) ($effect['value'] ?? 0);
            $effectKind = (string) ($effect['kind'] ?? '');
            if ($effectKind === 'persistent:' . $this->lower($kind)) {
                $modifier += (int) ($effect['value'] ?? 0);
            }
            if ($this->matchesNextTest($effectKind, $kind)) {
                $modifier += (int) ($effect['value'] ?? 0);
                unset($data['consumption']['effects'][$key]);
            }
        }
        return $modifier;
    }

    private function damage(array &$data, int $amount, array &$events): void
    {
        $wounds = max(0, $this->trait($data, 'zyw') - $amount);
        $this->setTrait($data, 'zyw', $wounds);
        if ($wounds <= 0 && $this->spendFate($data)) {
            $this->setTrait($data, 'zyw', 1);
            $events[] = 'Punkt Przeznaczenia ocalił ci życie.';
        }
    }

    private function healLightlyWounded(array &$data, int $amount): bool
    {
        $current = $this->trait($data, 'zyw');
        $maximum = $this->maximumWounds($data);
        if ($current >= $maximum || $current < (int) ceil($maximum / 2)) return false;
        $this->setTrait($data, 'zyw', min($maximum, $current + $amount));
        return true;
    }

    private function trait(array $data, string $key): int
    {
        $aliases = ['odp' => ['odp', 'toughness'], 'wt' => ['wt', 'toughnessBonus'], 'zyw' => ['zyw', 'wounds'], 'po' => ['po', 'fatePoints']][$key] ?? [$key];
        foreach (['actual', 'current', 'start', 'base'] as $layer) foreach ($aliases as $alias) if (isset($data['attributes'][$layer][$alias])) return max(0, (int) $data['attributes'][$layer][$alias]);
        foreach ($aliases as $alias) if (isset($data[$alias]['cur'])) return max(0, (int) $data[$alias]['cur']);
        return 0;
    }

    private function maximumWounds(array $data): int
    {
        $base = (int) ($data['attributes']['start']['zyw'] ?? $data['attributes']['base']['zyw'] ?? 0);
        $advance = (int) ($data['attributes']['advances']['zyw'] ?? 0);
        return max(1, $base + $advance, $this->trait($data, 'zyw'));
    }

    private function setTrait(array &$data, string $key, int $value): void
    {
        if (!isset($data['attributes']) || !is_array($data['attributes'])) $data['attributes'] = [];
        if (!isset($data['attributes']['actual']) || !is_array($data['attributes']['actual'])) $data['attributes']['actual'] = [];
        $data['attributes']['actual'][$key] = max(0, $value);
    }

    private function spendFate(array &$data): bool
    {
        $fate = $this->trait($data, 'po');
        if ($fate < 1) return false;
        $this->setTrait($data, 'po', $fate - 1);
        return true;
    }

    private function hasTalent(array $data, string $needle): bool
    {
        $entries = array_merge(
            (array) ($data['attributes']['talents'] ?? []), (array) ($data['talents'] ?? []),
            (array) ($data['attributes']['skills'] ?? []), (array) ($data['skills'] ?? [])
        );
        foreach ($entries as $talent) {
            $name = is_array($talent) ? ($talent['name'] ?? '') : $talent;
            if (mb_stripos((string) $name, $needle) !== false) return true;
        }
        return false;
    }

    private function testModifier(string $test): int
    {
        return preg_match('/([+-]\d+)%/u', $test, $match) ? (int) $match[1] : 0;
    }

    private function dice(int $count, int $sides): int
    {
        $total = 0;
        for ($i = 0; $i < $count; $i++) $total += random_int(1, $sides);
        return $total;
    }

    private function &consumptionData(array &$data): array
    {
        if (!isset($data['consumption']) || !is_array($data['consumption'])) $data['consumption'] = [];
        return $data['consumption'];
    }

    private function appendJournal(array &$data, array $events, int $minute): void
    {
        $consumption =& $this->consumptionData($data);
        $journal = array_values((array) ($consumption['journal'] ?? []));
        foreach ($events as $message) $journal[] = ['minute' => $minute, 'message' => (string) $message];
        $consumption['journal'] = array_slice($journal, -100);
    }

    private function lockedWorldState(int $campaignId): array
    {
        $this->db->query('INSERT IGNORE INTO campaign_consumption_states (campaign_id, world_minute, created_at, updated_at) VALUES (?, 0, NOW(), NOW())', [$campaignId]);
        return $this->db->query('SELECT * FROM campaign_consumption_states WHERE campaign_id = ? FOR UPDATE', [$campaignId])->getRowArray();
    }

    private function requestResult(int $campaignId, string $key): ?array
    {
        $row = $this->db->table('consumption_requests')->where(['campaign_id' => $campaignId, 'request_key' => $key])->get()->getRowArray();
        if (!$row || empty($row['result_json'])) return null;
        $result = json_decode((string) $row['result_json'], true);
        return is_array($result) ? $result : null;
    }

    private function characterForOwner(int $campaignId, string $ownerCode): ?array
    {
        $claim = $this->db->table('shop_owner_claims')->where(['campaign_id' => $campaignId, 'owner_code' => $ownerCode])->get()->getRowArray();
        $id = (int) ($claim['character_id'] ?? 0);
        if (!$id && preg_match('/^CHAR_(\d+)$/', $ownerCode, $match)) $id = (int) $match[1];
        if (!$id) return null;
        return $this->db->table('characters')->groupStart()
            ->where('campaign_id', $campaignId)->orWhere('campaign_id', null)
            ->groupEnd()->where('id', $id)->get()->getRowArray();
    }

    private function inActiveCombat(int $campaignId, int $characterId): bool
    {
        if (!$this->db->tableExists('scene_combats') || !$this->db->tableExists('scene_tokens')) return false;
        return $this->db->table('scene_combats combats')->join('scene_tokens tokens', 'tokens.scene_id = combats.scene_id')
            ->where(['combats.campaign_id' => $campaignId, 'combats.active' => 1, 'tokens.character_id' => $characterId])
            ->countAllResults() > 0;
    }

    private function decodeData($data): array
    {
        if (is_array($data)) return $data;
        $decoded = json_decode((string) $data, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveCharacter(array $character, array $data): void
    {
        $this->db->table('characters')->where('id', $character['id'])->set('data', json_encode($data, JSON_UNESCAPED_UNICODE))
            ->set('revision', 'revision + 1', false)->set('updated_at', date('Y-m-d H:i:s'))->update();
    }

    private function characterPayload(array $character, array $data): array
    {
        return ['id' => (int) $character['id'], 'data' => $data, 'revision' => (int) ($character['revision'] ?? 1) + 1];
    }

    private function positiveId($value, string $code): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) throw new ConsumptionException($code, 'Nie znaleziono przedmiotu.', 404);
        return (int) $id;
    }

    private function lower(string $value): string
    {
        return mb_strtolower(trim($value));
    }

    private function matchesNextTest(string $effectKind, string $testKind): bool
    {
        if ($effectKind === 'next_test') return true;
        if (strpos($effectKind, 'next_test:') !== 0) return false;
        $kind = $this->lower($testKind);
        $aliases = ['odporność' => 'odp', 'siła woli' => 'sw', 'ogłada' => 'ogd', 'inteligencja' => 'int', 'zręczność' => 'zr'];
        foreach (explode(',', substr($effectKind, 10)) as $candidate) {
            $candidate = $this->lower($candidate);
            if ($candidate === $kind || ($aliases[$kind] ?? '') === $candidate || ($aliases[$candidate] ?? '') === $kind) return true;
        }
        return false;
    }
}
