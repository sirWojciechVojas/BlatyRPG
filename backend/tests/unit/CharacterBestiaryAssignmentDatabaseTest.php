<?php

use App\Services\Bestiary\CharacterBestiaryService;
use App\Services\Campaign\CampaignException;
use App\Services\Campaign\CampaignGuardService;
use App\Services\Compendium\CompendiumAccessService;
use App\Services\Compendium\CompendiumCorpusService;
use App\Services\Compendium\CompendiumService;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Test\CIUnitTestCase;
use Config\Database;

final class CharacterBestiaryAssignmentDatabaseTest extends CIUnitTestCase
{
    private BaseConnection $bestiaryDb;
    private CharacterBestiaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bestiaryDb = Database::connect([
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
                    throw new CampaignException(
                        'campaign_not_found',
                        'Campaign was not found.',
                        404
                    );
                }
                $manager = (int) ($auth['user_id'] ?? 0) === 1;
                return [
                    'auth' => $auth,
                    'campaign' => [
                        'id' => 7,
                        'rpg_universe_id' => 9,
                    ],
                    'isAdmin' => false,
                    'isGameMaster' => $manager,
                    'campaignRole' => $manager ? 'game_master' : 'player',
                    'capabilities' => [
                        'canManage' => $manager,
                        'canManageCharacters' => $manager,
                    ],
                ];
            }
        };
        $access = new CompendiumAccessService($this->bestiaryDb, $guard);
        $compendium = new CompendiumService($this->bestiaryDb, $access);
        $this->service = new CharacterBestiaryService(
            $this->bestiaryDb,
            $access,
            $compendium
        );
    }

    protected function tearDown(): void
    {
        $this->bestiaryDb->close();
        parent::tearDown();
    }

    public function testGmSetsIndependentKnowledgeForEveryHero(): void
    {
        $this->service->setAssignment(
            7,
            51,
            11,
            $this->gm(),
            ['level' => 'summary']
        );
        $this->service->setAssignment(
            7,
            51,
            12,
            $this->gm(),
            ['level' => 'full']
        );

        $states = $this->service->assignments(7, 51, $this->gm());
        $this->assertSame(
            ['Adelinde', 'Gustav', 'Legacy Hero'],
            array_column($states['assignments'], 'characterName')
        );
        $this->assertSame(
            ['summary', 'full', 'unknown'],
            array_column($states['assignments'], 'level')
        );

        $adelinde = $this->service->index(7, 11, $this->player(2));
        $this->assertCount(0, $adelinde['encountered']);
        $this->assertSame([51], array_column($adelinde['remaining'], 'id'));
        $this->assertSame('summary', $adelinde['remaining'][0]['level']);
        $this->assertSame('Horned Reaper', $adelinde['remaining'][0]['title']);

        $gustav = $this->service->index(7, 12, $this->player(3));
        $this->assertSame([51], array_column($gustav['encountered'], 'id'));
        $this->assertCount(0, $gustav['remaining']);

        $this->service->setAssignment(
            7,
            51,
            12,
            $this->gm(),
            ['level' => 'unknown']
        );
        $gustav = $this->service->index(7, 12, $this->player(3));
        $this->assertSame(1, $gustav['counts']['total']);
        $this->assertSame('unknown', $gustav['remaining'][0]['level']);
        $this->assertArrayNotHasKey('title', $gustav['remaining'][0]);
        $this->assertSame(
            0,
            $this->bestiaryDb->table('character_bestiary_encounters')
                ->where('character_id', 12)
                ->countAllResults()
        );
    }

    public function testPlayerCannotAssignBestiaryKnowledge(): void
    {
        $this->expectException(CampaignException::class);
        $this->service->setAssignment(
            7,
            51,
            11,
            $this->player(2),
            ['level' => 'full']
        );
    }

    public function testSummaryLevelDoesNotPermitReadingTheFullArticle(): void
    {
        $this->service->setAssignment(
            7,
            51,
            11,
            $this->gm(),
            ['level' => 'summary']
        );

        try {
            $this->service->show(7, 11, 51, $this->player(2));
            $this->fail('Summary knowledge must not expose the full article.');
        } catch (CampaignException $exception) {
            $this->assertSame('bestiary_entry_locked', $exception->errorCode());
        }
    }

    public function testGmUpdatesManyHeroAssignmentsInOneTransaction(): void
    {
        $granted = $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'characterIds' => [11, 12, 14],
                'level' => 'summary',
            ]
        );

        $this->assertSame(
            [11, 12, 14],
            array_column($granted['assignments'], 'characterId')
        );
        $this->assertSame(
            ['summary', 'summary', 'summary'],
            array_column($granted['assignments'], 'level')
        );
        $this->assertSame(
            3,
            $this->bestiaryDb->table('compendium_campaign_reveals')
                ->where('campaign_id', 7)
                ->where('revoked_at', null)
                ->countAllResults()
        );

        $unknown = $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'characterIds' => [11, 12, 14],
                'level' => 'unknown',
            ]
        );

        $this->assertSame(
            ['unknown', 'unknown', 'unknown'],
            array_column($unknown['assignments'], 'level')
        );
        $this->assertSame(
            0,
            $this->bestiaryDb->table('compendium_campaign_reveals')
                ->where('campaign_id', 7)
                ->where('revoked_at', null)
                ->countAllResults()
        );
        $this->assertSame(
            ['unknown', 'unknown', 'unknown'],
            array_column(
                $this->bestiaryDb->table('character_bestiary_knowledge')
                    ->select('knowledge_level')
                    ->orderBy('character_id', 'ASC')
                    ->get()
                    ->getResultArray(),
                'knowledge_level'
            )
        );
    }

    public function testGmSavesDifferentDraftLevelsInOneTransaction(): void
    {
        $saved = $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'assignments' => [
                    ['characterId' => 11, 'level' => 'unknown'],
                    ['characterId' => 12, 'level' => 'summary'],
                    ['characterId' => 14, 'level' => 'full'],
                ],
            ]
        );

        $this->assertSame(
            ['unknown', 'summary', 'full'],
            array_column($saved['assignments'], 'level')
        );
        $this->assertSame(
            ['unknown', 'summary', 'full'],
            array_column(
                $this->bestiaryDb->table('character_bestiary_knowledge')
                    ->select('knowledge_level')
                    ->orderBy('character_id', 'ASC')
                    ->get()
                    ->getResultArray(),
                'knowledge_level'
            )
        );
        $this->assertSame(
            2,
            $this->bestiaryDb->table('compendium_campaign_reveals')
                ->where('campaign_id', 7)
                ->where('revoked_at', null)
                ->countAllResults()
        );
    }

    public function testGmPublishesOnlySelectedSourceSectionsToEveryHero(): void
    {
        $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'characterIds' => [11, 12],
                'level' => 'full',
            ]
        );

        $saved = $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'assignments' => [],
                'sectionKeys' => ['lead', 'habitat'],
            ]
        );

        $this->assertSame(['lead', 'habitat'], $saved['sectionKeys']);
        $this->assertSame([], $saved['assignments']);
        $this->assertSame(
            ['lead', 'habitat'],
            json_decode(
                $this->bestiaryDb
                    ->table('campaign_bestiary_entry_content')
                    ->select('section_keys_json')
                    ->where('campaign_id', 7)
                    ->where('entry_id', 51)
                    ->get()
                    ->getRowArray()['section_keys_json'],
                true
            )
        );
        $reveals = $this->bestiaryDb
            ->table('compendium_campaign_reveals')
            ->select('section_keys_json, snapshot_search_text')
            ->where('campaign_id', 7)
            ->where('revoked_at', null)
            ->get()
            ->getResultArray();
        $this->assertCount(2, $reveals);
        foreach ($reveals as $reveal) {
            $this->assertSame(
                ['lead', 'habitat'],
                json_decode($reveal['section_keys_json'], true)
            );
            $this->assertStringContainsString(
                'secret forest lore',
                $reveal['snapshot_search_text']
            );
            $this->assertStringNotContainsString(
                'secret combat lore',
                $reveal['snapshot_search_text']
            );
        }

        $assignments = $this->service->assignments(7, 51, $this->gm());
        $this->assertSame(['lead', 'habitat'], $assignments['sectionKeys']);
    }

    public function testCharacterReaderCannotBypassSelectionOnPublicEntry(): void
    {
        $this->service->setAssignments(
            7,
            51,
            $this->gm(),
            [
                'characterIds' => [11],
                'level' => 'full',
                'sectionKeys' => ['lead'],
            ]
        );
        $this->bestiaryDb->table('compendium_source_revisions')->insert([
            'id' => 72,
            'sanitized_html' => '<p>New unrestricted revision.</p>',
            'sections_json' => '[{"id":"lead","title":"Introduction"}]',
        ]);

        $method = new \ReflectionMethod(
            CompendiumCorpusService::class,
            'allowedRevision'
        );
        $method->setAccessible(true);
        $revision = $method->invoke(
            new CompendiumCorpusService($this->bestiaryDb),
            [
                'canSeeGm' => true,
                'campaign' => ['id' => 7],
                'auth' => ['user_id' => 1],
                'characterBestiaryCharacterId' => 11,
            ],
            [
                'id' => 81,
                'visibility' => 'public',
                'verification_status' => 'verified',
                'current_source_revision_id' => 72,
            ]
        );

        $this->assertSame(71, (int) $revision['id']);
        $this->assertSame(
            ['lead'],
            json_decode($revision['_reveal']['section_keys_json'], true)
        );
    }

    public function testBulkAssignmentRejectsNpcWithoutPartialChanges(): void
    {
        try {
            $this->service->setAssignments(
                7,
                51,
                $this->gm(),
                [
                    'characterIds' => [11, 13],
                    'level' => 'summary',
                ]
            );
            $this->fail('The NPC assignment should have been rejected.');
        } catch (CampaignException $exception) {
            $this->assertSame('character_not_found', $exception->errorCode());
        }

        $this->assertSame(
            0,
            $this->bestiaryDb->table('compendium_campaign_reveals')
                ->countAllResults()
        );
    }

    public function testGmOwnedNpcCannotReceivePlayerBestiaryAccess(): void
    {
        $this->expectException(CampaignException::class);
        $this->service->setAssignment(
            7,
            51,
            13,
            $this->gm(),
            ['level' => 'summary']
        );
    }

    private function gm(): array
    {
        return ['user_id' => 1, 'role' => 'user'];
    }

    private function player(int $userId): array
    {
        return ['user_id' => $userId, 'role' => 'user'];
    }

    private function createSchema(): void
    {
        foreach ([
            'CREATE TABLE rpg_universes (id INTEGER PRIMARY KEY, name TEXT)',
            'CREATE TABLE compendium_worlds (id INTEGER PRIMARY KEY, universe_id INTEGER, owner_user_id INTEGER, storage_limit_bytes INTEGER, revision INTEGER, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE characters (id INTEGER PRIMARY KEY, user_id INTEGER, campaign_id INTEGER, name TEXT, data TEXT)',
            'CREATE TABLE character_campaigns (id INTEGER PRIMARY KEY, character_id INTEGER, campaign_id INTEGER)',
            'CREATE TABLE campaign_members (id INTEGER PRIMARY KEY, campaign_id INTEGER, user_id INTEGER, role TEXT, is_active INTEGER)',
            'CREATE TABLE resource_permissions (id INTEGER PRIMARY KEY, campaign_id INTEGER, resource_type TEXT, resource_id INTEGER, user_id INTEGER, access_level TEXT)',
            'CREATE TABLE compendium_entry_types (id INTEGER PRIMARY KEY, world_id INTEGER, code TEXT, name TEXT, icon TEXT, is_builtin INTEGER, field_schema_json TEXT)',
            'CREATE TABLE compendium_entries (id INTEGER PRIMARY KEY, world_id INTEGER, slug TEXT, published_version_id INTEGER, status TEXT, deleted_at TEXT)',
            'CREATE TABLE compendium_entry_versions (id INTEGER PRIMARY KEY, entry_id INTEGER, type_id INTEGER, title TEXT, excerpt TEXT)',
            'CREATE TABLE compendium_source_revisions (id INTEGER PRIMARY KEY, sanitized_html TEXT, sections_json TEXT)',
            'CREATE TABLE compendium_entities (id INTEGER PRIMARY KEY, world_id INTEGER, entry_id INTEGER, current_source_revision_id INTEGER, name TEXT, deleted_at TEXT)',
            'CREATE TABLE compendium_campaign_reveals (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, entity_id INTEGER, source_revision_id INTEGER, user_id INTEGER, character_id INTEGER, section_keys_json TEXT, snapshot_search_text TEXT, granted_by_user_id INTEGER, revoked_at TEXT, created_at TEXT, updated_at TEXT)',
            'CREATE TABLE character_bestiary_encounters (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, entry_id INTEGER, first_seen_scene_id INTEGER, first_seen_token_id INTEGER, discovered_at TEXT)',
            'CREATE TABLE character_bestiary_knowledge (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, character_id INTEGER, entry_id INTEGER, knowledge_level TEXT, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(campaign_id, character_id, entry_id))',
            'CREATE TABLE campaign_bestiary_entry_content (id INTEGER PRIMARY KEY AUTOINCREMENT, campaign_id INTEGER, entry_id INTEGER, section_keys_json TEXT, updated_by_user_id INTEGER, created_at TEXT, updated_at TEXT, UNIQUE(campaign_id, entry_id))',
        ] as $sql) {
            $this->bestiaryDb->query($sql);
        }
    }

    private function seed(): void
    {
        $this->bestiaryDb->table('rpg_universes')->insert([
            'id' => 9,
            'name' => 'The Old World',
        ]);
        $this->bestiaryDb->table('compendium_worlds')->insert([
            'id' => 3,
            'universe_id' => 9,
            'owner_user_id' => 1,
            'storage_limit_bytes' => 1000000,
            'revision' => 1,
            'created_at' => '2026-09-21 12:00:00',
            'updated_at' => '2026-09-21 12:00:00',
        ]);
        $this->bestiaryDb->table('characters')->insertBatch([
            ['id' => 11, 'user_id' => 2, 'campaign_id' => 7, 'name' => 'Adelinde', 'data' => '{}'],
            ['id' => 12, 'user_id' => 3, 'campaign_id' => 7, 'name' => 'Gustav', 'data' => '{}'],
            ['id' => 13, 'user_id' => 1, 'campaign_id' => 7, 'name' => 'Bruder Witz', 'data' => '{}'],
            ['id' => 14, 'user_id' => null, 'campaign_id' => 7, 'name' => 'Legacy Hero', 'data' => '{"meta":{"gamer_name":"Radek"}}'],
        ]);
        $this->bestiaryDb->table('character_campaigns')->insertBatch([
            ['id' => 1, 'character_id' => 11, 'campaign_id' => 7],
            ['id' => 2, 'character_id' => 12, 'campaign_id' => 7],
            ['id' => 3, 'character_id' => 13, 'campaign_id' => 7],
            ['id' => 4, 'character_id' => 14, 'campaign_id' => 7],
        ]);
        $this->bestiaryDb->table('campaign_members')->insertBatch([
            ['id' => 1, 'campaign_id' => 7, 'user_id' => 1, 'role' => 'gm', 'is_active' => 1],
            ['id' => 2, 'campaign_id' => 7, 'user_id' => 2, 'role' => 'player', 'is_active' => 1],
            ['id' => 3, 'campaign_id' => 7, 'user_id' => 3, 'role' => 'player', 'is_active' => 1],
        ]);
        $this->bestiaryDb->table('compendium_entry_types')->insert([
            'id' => 21,
            'world_id' => 3,
            'code' => 'creature',
            'name' => 'Bestiary',
            'icon' => 'paw',
            'is_builtin' => 1,
            'field_schema_json' => '[]',
        ]);
        $this->bestiaryDb->table('compendium_entries')->insert([
            'id' => 51,
            'world_id' => 3,
            'slug' => 'horned-reaper',
            'published_version_id' => 61,
            'status' => 'active',
            'deleted_at' => null,
        ]);
        $this->bestiaryDb->table('compendium_entry_versions')->insert([
            'id' => 61,
            'entry_id' => 51,
            'type_id' => 21,
            'title' => 'Horned Reaper',
            'excerpt' => 'A dangerous forest creature.',
        ]);
        $this->bestiaryDb->table('compendium_source_revisions')->insert([
            'id' => 71,
            'sanitized_html' => '<p>Short introduction.</p><h2 id="habitat">Habitat</h2><p>Secret forest lore.</p><h2 id="combat">Combat</h2><p>Secret combat lore.</p>',
            'sections_json' => json_encode([
                ['id' => 'lead', 'title' => 'Introduction', 'level' => 1],
                ['id' => 'habitat', 'title' => 'Habitat', 'level' => 2],
                ['id' => 'combat', 'title' => 'Combat', 'level' => 2],
            ]),
        ]);
        $this->bestiaryDb->table('compendium_entities')->insert([
            'id' => 81,
            'world_id' => 3,
            'entry_id' => 51,
            'current_source_revision_id' => 71,
            'name' => 'Horned Reaper',
            'deleted_at' => null,
        ]);
    }
}
