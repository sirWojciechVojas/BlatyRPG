<?php

use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Magic\HeroMagicService;
use App\Services\Magic\Wfrp2MagicResolver;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class HeroMagicServiceDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $magicDb;
    private HeroMagicService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->magicDb = Database::connect([
            'DBDriver' => 'SQLite3',
            'database' => ':memory:',
            'DBPrefix' => '',
        ], false);
        $this->createSchema();
        $this->seed();

        $guard = new class extends CampaignGuardService {
            public function __construct()
            {
            }

            public function context(array $auth, int $campaignId): array
            {
                if ($campaignId !== 7) {
                    throw new CampaignException('campaign_not_found', 'Campaign was not found.', 404);
                }
                $canManage = (int) ($auth['user_id'] ?? 0) === 1;
                return [
                    'auth' => $auth,
                    'campaign' => ['id' => 7],
                    'capabilities' => ['canManage' => $canManage],
                ];
            }

            public function requireManage(array $auth, int $campaignId): array
            {
                $context = $this->context($auth, $campaignId);
                if (!$context['capabilities']['canManage']) {
                    throw new CampaignException('forbidden', 'Manager access is required.', 403);
                }
                return $context;
            }
        };

        $rolls = [6, 6];
        $resolver = new Wfrp2MagicResolver(static function () use (&$rolls): int {
            return array_shift($rolls) ?? 1;
        });
        $this->service = new HeroMagicService(
            $this->magicDb,
            $guard,
            $resolver,
            static fn (): int => 40
        );
    }

    protected function tearDown(): void
    {
        $this->magicDb->close();
        parent::tearDown();
    }

    public function testLearningChargesExperienceOnlyOnceAfterGmApproval(): void
    {
        $before = $this->characterData();
        $request = $this->service->requestLearning(7, 9, $this->player(), [
            'type' => 'spell',
            'spellId' => 11,
            'source' => 'Księga mistrza',
            'idempotencyKey' => 'learn-extra-11',
        ]);

        $this->assertSame(0, $request['experienceSpent']);
        $this->assertSame(200, $before['experience']['current']);
        $this->assertSame(200, $this->characterData()['experience']['current']);

        $approved = $this->service->decideLearning(
            7,
            (int) $request['request']['id'],
            $this->gm(),
            [
                'decision' => 'approve',
                'note' => 'Nauka zakończona.',
                'gmPrivateNote' => 'Sekretny warunek fabularny.',
                'idempotencyKey' => 'approve-extra-11',
            ]
        );
        $duplicate = $this->service->decideLearning(
            7,
            (int) $request['request']['id'],
            $this->gm(),
            [
                'decision' => 'approve',
                'idempotencyKey' => 'approve-extra-11-repeat',
            ]
        );

        $this->assertSame(100, $approved['experienceSpent']);
        $this->assertSame(100, $approved['experienceAfter']);
        $this->assertTrue($duplicate['duplicate']);
        $this->assertSame(100, $duplicate['experienceSpent']);
        $this->assertSame(100, $this->characterData()['experience']['current']);
        $this->assertSame(1, $this->magicDb->table('hero_spell_grants')->countAllResults());
        $this->assertSame(1, $this->magicDb->table('hero_experience_expenses')->countAllResults());
        $this->assertArrayNotHasKey('gmPrivateNote', $approved['request']);
        $this->assertStringNotContainsString(
            'Sekretny warunek fabularny',
            json_encode($this->service->history(7, 9, $this->player()), JSON_UNESCAPED_UNICODE)
        );
    }

    public function testApprovedPathGrantsExactlyTenSpellsWithoutChargingExperience(): void
    {
        $this->seedCompletePath();
        $this->magicDb->table('hero_magic_profiles')->where('id', 1)->update([
            'path_id' => null,
            'has_arcane_magic' => 0,
        ]);

        $request = $this->service->requestLearning(7, 9, $this->player(), [
            'type' => 'path',
            'pathId' => 5,
            'idempotencyKey' => 'learn-fire-path',
        ]);
        $approved = $this->service->decideLearning(
            7,
            (int) $request['request']['id'],
            $this->gm(),
            [
                'decision' => 'approve',
                'idempotencyKey' => 'approve-fire-path',
            ]
        );

        $this->assertSame(0, $request['experienceSpent']);
        $this->assertSame(0, $approved['experienceSpent']);
        $this->assertSame(200, $this->characterData()['experience']['current']);
        $this->assertSame(10, $this->magicDb->table('hero_spell_grants')->countAllResults());
        $profile = $this->magicDb->table('hero_magic_profiles')->where('id', 1)
            ->get()->getRowArray();
        $this->assertSame(5, (int) $profile['path_id']);
        $this->assertSame(1, (int) $profile['has_arcane_magic']);
    }

    public function testFailedCastStillConsumesIngredientAndReportsChaosManifestation(): void
    {
        $this->grantSpell();
        $created = $this->service->createCast(7, 9, $this->player(), [
            'spellId' => 11,
            'powerDice' => 1,
            'ingredient' => ['kind' => 'template_stack', 'id' => 31],
            'targets' => [['type' => 'descriptive', 'label' => 'Kultysta']],
            'idempotencyKey' => 'cast-fireball-11',
        ]);
        $resolved = $this->service->resolveCast(
            7,
            (int) $created['cast']['id'],
            $this->player()
        );
        $duplicate = $this->service->resolveCast(
            7,
            (int) $created['cast']['id'],
            $this->player()
        );
        $result = $resolved['cast']['result'];

        $this->assertFalse($result['spellSucceeded']);
        $this->assertSame(8, $result['powerTotal']);
        $this->assertSame([6], $result['powerDice']);
        $this->assertSame([6], $result['chaosDice']);
        $this->assertSame('minor', $result['manifestations'][0]['severity']);
        $this->assertSame('not_applicable', $result['targetDefense']['status']);
        $this->assertTrue($result['ingredient']['consumed']);
        $this->assertSame(
            1,
            (int) $this->magicDb->table('shop_container_template_items')
                ->where('id', 31)->get()->getRowArray()['quantity']
        );
        $this->assertTrue($duplicate['duplicate']);
    }

    public function testMagicZeroIsBlockedByTheServer(): void
    {
        $this->grantSpell();
        $data = $this->characterData();
        $data['attributes']['actual']['mag'] = 0;
        $this->magicDb->table('characters')->where('id', 9)->update([
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
        ]);

        try {
            $this->service->createCast(7, 9, $this->player(), [
                'spellId' => 11,
                'powerDice' => 1,
                'idempotencyKey' => 'cast-magic-zero',
            ]);
            $this->fail('Mag 0 must be rejected by the server.');
        } catch (CampaignException $exception) {
            $this->assertSame('magic_zero', $exception->errorCode());
            $this->assertSame(409, $exception->status());
        }
    }

    public function testRitualConceptStoresRequirementsIngredientsTimeAndConsequences(): void
    {
        $created = $this->service->createRitual(7, 9, $this->player(), [
            'name' => 'Krąg popiołu',
            'effect' => 'Ochrona uczestników przed ogniem i dymem.',
            'requirements' => ['Mag 2', 'Język magiczny'],
            'ingredients' => ['Srebrny pył', 'Trzy świece'],
            'castingTime' => '8 godzin świata kampanii',
            'knownConsequences' => 'Niepowodzenie niszczy krąg.',
        ]);
        $ritual = $created['ritual'];

        $this->assertSame('concept', $ritual['stage']);
        $this->assertSame(['Mag 2', 'Język magiczny'], $ritual['requirements']);
        $this->assertSame(['Srebrny pył', 'Trzy świece'], $ritual['ingredients']);
        $this->assertSame('8 godzin świata kampanii', $ritual['castingTime']);
        $this->assertSame('Niepowodzenie niszczy krąg.', $ritual['knownConsequences']);
    }

    private function player(): array
    {
        return ['user_id' => 2, 'role' => 'user'];
    }

    private function gm(): array
    {
        return ['user_id' => 1, 'role' => 'user'];
    }

    private function characterData(): array
    {
        return json_decode(
            (string) $this->magicDb->table('characters')->select('data')
                ->where('id', 9)->get()->getRowArray()['data'],
            true
        );
    }

    private function grantSpell(): void
    {
        $this->magicDb->table('hero_spell_grants')->insert([
            'campaign_id' => 7,
            'character_id' => 9,
            'spell_id' => 11,
            'grant_type' => 'path_package',
            'source_reference' => 'path:elemental',
            'granted_by_user_id' => 1,
            'granted_at' => '2026-09-23 10:00:00',
            'verification_status' => 'verified',
        ]);
    }

    private function seedCompletePath(): void
    {
        $baseSpell = $this->magicDb->table('hero_magic_spells')->where('id', 11)
            ->get()->getRowArray();
        for ($index = 0; $index < 10; $index++) {
            $spellId = 11 + $index;
            if ($index > 0) {
                $spell = $baseSpell;
                $spell['id'] = $spellId;
                $spell['code'] = 'fire-path-' . $spellId;
                $spell['name_pl'] = 'Czar Ognia ' . $spellId;
                $this->magicDb->table('hero_magic_spells')->insert($spell);
            }
            $this->magicDb->table('hero_magic_path_spells')->insert([
                'path_id' => 5,
                'spell_id' => $spellId,
                'sort_order' => $index + 1,
            ]);
        }
    }

    private function seed(): void
    {
        $now = '2026-09-23 10:00:00';
        $this->magicDb->table('rpg_systems')->insert(['id' => 1, 'code' => 'wfrp2ed']);
        $this->magicDb->table('characters')->insert([
            'id' => 9,
            'user_id' => 2,
            'campaign_id' => 7,
            'system_id' => 1,
            'name' => 'Alaric',
            'data' => json_encode([
                'attributes' => ['actual' => ['mag' => 2, 'sw' => 45]],
                'experience' => ['current' => 200],
            ], JSON_UNESCAPED_UNICODE),
            'revision' => 1,
            'updated_at' => $now,
        ]);
        $this->magicDb->table('hero_magic_traditions')->insert([
            'id' => 3,
            'system_code' => 'wfrp2ed',
            'setting_code' => 'old_world',
            'code' => 'fire',
            'name_pl' => 'Ognia',
            'name_en' => 'Fire',
            'wind_code' => 'Aqshy',
            'college_pl' => 'Płomienia',
            'accent_color' => '#9e3928',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->magicDb->table('hero_magic_paths')->insert([
            'id' => 5,
            'tradition_id' => 3,
            'code' => 'elemental',
            'name_pl' => 'Żywiołu',
            'name_en' => 'Elemental',
            'verified_complete' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->magicDb->table('hero_magic_spells')->insert([
            'id' => 11,
            'tradition_id' => 3,
            'system_code' => 'wfrp2ed',
            'setting_code' => 'old_world',
            'code' => 'fireball',
            'name_pl' => 'Ognista kula',
            'name_en' => 'Fireball',
            'magic_type' => 'arcane',
            'casting_number' => 12,
            'casting_time' => '1 akcja',
            'range_text' => '48 m',
            'target_type' => 'missiles',
            'duration_text' => 'Natychmiastowy',
            'ingredient_name' => 'Bryłka siarki',
            'ingredient_bonus' => 2,
            'effect_summary' => 'Mag pocisków o Sile 3.',
            'defense_text' => null,
            'special_rules_json' => '{}',
            'source_title' => 'Królestwa Magii',
            'source_page' => 151,
            'source_version' => 'pl-2e',
            'verification_status' => 'verified',
            'definition_version' => 1,
            'is_published' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->magicDb->table('hero_magic_profiles')->insert([
            'id' => 1,
            'campaign_id' => 7,
            'character_id' => 9,
            'tradition_id' => 3,
            'path_id' => 5,
            'has_arcane_magic' => 1,
            'chaos_dice' => 1,
            'revision' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->magicDb->table('hero_spell_reveals')->insert([
            'campaign_id' => 7,
            'character_id' => 9,
            'spell_id' => 11,
            'revealed_by_user_id' => 1,
            'source_label' => 'Księga mistrza',
            'revealed_at' => $now,
        ]);
        $this->magicDb->table('shop_containers')->insert([
            'id' => 20,
            'campaign_id' => 7,
            'container_type' => 'CHARACTER',
            'owner_code' => 'CHAR_9',
            'is_active' => 1,
        ]);
        $this->magicDb->table('shop_templates')->insert([
            'id' => 30,
            'name' => 'Bryłka siarki',
            'deleted_at' => null,
        ]);
        $this->magicDb->table('shop_container_template_items')->insert([
            'id' => 31,
            'campaign_id' => 7,
            'container_id' => 20,
            'template_id' => 30,
            'quantity' => 2,
        ]);
    }

    private function createSchema(): void
    {
        $statements = [
            'CREATE TABLE rpg_systems (id INTEGER PRIMARY KEY, code TEXT)',
            'CREATE TABLE characters (id INTEGER PRIMARY KEY, user_id INTEGER, campaign_id INTEGER, system_id INTEGER, name TEXT, data TEXT, revision INTEGER, updated_at TEXT)',
            'CREATE TABLE hero_magic_traditions (id INTEGER PRIMARY KEY, system_code TEXT, setting_code TEXT, code TEXT, name_pl TEXT, name_en TEXT, wind_code TEXT, college_pl TEXT, accent_color TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE hero_magic_paths (id INTEGER PRIMARY KEY, tradition_id INTEGER, code TEXT, name_pl TEXT, name_en TEXT, verified_complete INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE hero_magic_spells (id INTEGER PRIMARY KEY, tradition_id INTEGER, system_code TEXT, setting_code TEXT, code TEXT, name_pl TEXT, name_en TEXT, magic_type TEXT, casting_number INTEGER, casting_time TEXT, range_text TEXT, target_type TEXT, duration_text TEXT, ingredient_name TEXT, ingredient_bonus INTEGER, effect_summary TEXT, defense_text TEXT, special_rules_json TEXT, source_title TEXT, source_page INTEGER, source_version TEXT, verification_status TEXT, definition_version INTEGER, is_published INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE hero_magic_path_spells (path_id INTEGER, spell_id INTEGER, sort_order INTEGER, PRIMARY KEY (path_id, spell_id))',
            'CREATE TABLE hero_magic_profiles (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, tradition_id INTEGER, path_id INTEGER, has_arcane_magic INTEGER, chaos_dice INTEGER, revision INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE hero_spell_grants (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, spell_id INTEGER, grant_type TEXT, source_reference TEXT, granted_by_user_id INTEGER, granted_at TEXT, verification_status TEXT, UNIQUE (campaign_id, character_id, spell_id))',
            'CREATE TABLE hero_spell_preferences (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, spell_id INTEGER, is_favorite INTEGER, is_pinned INTEGER, pin_order INTEGER, personal_note TEXT, updated_at TEXT)',
            'CREATE TABLE hero_spell_reveals (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, spell_id INTEGER, revealed_by_user_id INTEGER, source_label TEXT, revealed_at TEXT)',
            'CREATE TABLE spell_learning_requests (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, request_type TEXT, spell_id INTEGER, path_id INTEGER, xp_cost INTEGER, source_label TEXT, player_note TEXT, world_date TEXT, status TEXT, public_decision_note TEXT, gm_private_note TEXT, idempotency_key TEXT, requested_by_user_id INTEGER, decided_by_user_id INTEGER, decided_at TEXT, created_at TEXT, updated_at TEXT, UNIQUE (campaign_id, character_id, idempotency_key))',
            'CREATE TABLE hero_experience_expenses (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, learning_request_id INTEGER UNIQUE, amount INTEGER, balance_after INTEGER, created_at TEXT)',
            'CREATE TABLE spell_casts (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, spell_id INTEGER, idempotency_key TEXT, status TEXT, power_dice_count INTEGER, chaos_dice_count INTEGER, actions_required INTEGER, actions_completed INTEGER, ingredient_kind TEXT, ingredient_item_id INTEGER, ingredient_name TEXT, ingredient_bonus INTEGER, targets_json TEXT, channel_attempted INTEGER, channel_succeeded INTEGER, channel_roll INTEGER, channel_bonus INTEGER, spell_snapshot_json TEXT, result_json TEXT, resolved_at TEXT, cancelled_at TEXT, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE (campaign_id, character_id, idempotency_key))',
            'CREATE TABLE cast_dice (id INTEGER PRIMARY KEY AUTOINCREMENT, cast_id INTEGER, die_role TEXT, die_order INTEGER, result INTEGER, included_in_power INTEGER, included_in_curse INTEGER, included_in_automatic_failure INTEGER)',
            'CREATE TABLE ritual_research (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, name TEXT, intended_effect TEXT, research_stage TEXT, ritual_type TEXT, language TEXT, minimum_magic INTEGER, learning_cost_xp INTEGER, casting_number INTEGER, casting_time TEXT, requirements_json TEXT, ingredients_json TEXT, known_consequences TEXT, gm_private_json TEXT, is_author_research INTEGER, status TEXT, revision INTEGER, created_by_user_id INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE hero_magic_history (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, event_type TEXT, reference_type TEXT, reference_id INTEGER, public_payload_json TEXT, gm_payload_json TEXT, created_by_user_id INTEGER, created_at TEXT)',
            'CREATE TABLE shop_containers (id INTEGER PRIMARY KEY, campaign_id INTEGER, container_type TEXT, owner_code TEXT, is_active INTEGER)',
            'CREATE TABLE shop_templates (id INTEGER PRIMARY KEY, name TEXT, deleted_at TEXT)',
            'CREATE TABLE shop_container_template_items (id INTEGER PRIMARY KEY, campaign_id INTEGER, container_id INTEGER, template_id INTEGER, quantity INTEGER)',
            'CREATE TABLE shop_item_instances (id INTEGER PRIMARY KEY, template_id INTEGER, name_override TEXT)',
            'CREATE TABLE shop_container_instance_items (id INTEGER PRIMARY KEY, campaign_id INTEGER, container_id INTEGER, instance_id INTEGER)',
        ];
        foreach ($statements as $statement) {
            $this->magicDb->query($statement);
        }
    }
}
