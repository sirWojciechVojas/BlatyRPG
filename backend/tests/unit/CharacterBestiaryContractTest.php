<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CharacterBestiaryContractTest extends CIUnitTestCase
{
    public function testMigrationKeepsDiscoveriesPerCampaignAndCharacter(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/2026-09-21-100000_CreateCharacterBestiary.php'
        );

        $this->assertStringContainsString(
            "['campaign_id', 'character_id', 'entry_id']",
            $source
        );
        $this->assertStringContainsString(
            "'entry_id', 'compendium_entries'",
            $source
        );
        $this->assertStringContainsString(
            "'first_seen_token_id', 'scene_tokens'",
            $source
        );
    }

    public function testRevealsCanTargetOnePlayerCharacter(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/'
            . '2026-09-21-110000_AddCharacterTargetsToCompendiumReveals.php'
        );

        $this->assertStringContainsString("'character_id'", $source);
        $this->assertStringContainsString(
            'fk_compendium_reveal_character',
            $source
        );
    }

    public function testKnowledgeLevelsAreStoredPerCampaignCharacterAndEntry(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/'
            . '2026-09-22-110000_CreateCharacterBestiaryKnowledge.php'
        );

        $this->assertStringContainsString(
            "['campaign_id', 'character_id', 'entry_id']",
            $source
        );
        $this->assertStringContainsString("'knowledge_level'", $source);
        $this->assertStringContainsString(
            'backfillExistingKnowledge',
            $source
        );
    }

    public function testSelectedSourceSectionsAreStoredPerCampaignAndEntry(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/'
            . '2026-09-22-120000_CreateCampaignBestiaryEntryContent.php'
        );

        $this->assertStringContainsString(
            "['campaign_id', 'entry_id']",
            $source
        );
        $this->assertStringContainsString("'section_keys_json'", $source);
        $this->assertStringContainsString('backfillSelections', $source);
    }

    public function testRecorderRunsAfterTokenVisibilityAndFogFiltering(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Token/SceneTokenService.php'
        );
        $fog = strpos($source, '$this->visibility->filter(');
        $record = strpos($source, '$this->bestiary->recordVisibleTokens(');

        $this->assertNotFalse($fog);
        $this->assertNotFalse($record);
        $this->assertGreaterThan($fog, $record);
    }

    public function testLockedEntryRequiresFullKnowledgeBeforeCompendiumRead(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Bestiary/CharacterBestiaryService.php'
        );
        $guard = strpos($source, "'bestiary_entry_locked'");
        $read = strpos($source, '$this->compendium->campaignBestiaryShow(');

        $this->assertNotFalse($guard);
        $this->assertNotFalse($read);
        $this->assertGreaterThan($guard, $read);
    }

    public function testCatalogShowsAllCreaturesAndComputesCharacterKnowledge(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Bestiary/CharacterBestiaryService.php'
        );

        $this->assertStringContainsString(
            'character_bestiary_knowledge knowledge',
            $source
        );
        $this->assertStringContainsString(
            'knowledge.knowledge_level',
            $source
        );
        $this->assertStringNotContainsString(
            "entity.visibility IN ('public','player')",
            $source
        );
    }

    public function testGenericCompendiumHidesCreatureEntriesFromPlayers(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );

        $this->assertStringContainsString(
            "where('access_type.code !=', 'creature')",
            $source
        );
        $this->assertStringContainsString(
            'campaignBestiaryShow',
            $source
        );
    }

    public function testCharacterReaderAlwaysUsesItsSectionReveal(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );
        $characterGuard = strpos(
            $source,
            "context['characterBestiaryCharacterId']"
        );
        $publicShortcut = strpos(
            $source,
            "entity['verification_status'] === 'verified'"
        );

        $this->assertNotFalse($characterGuard);
        $this->assertNotFalse($publicShortcut);
        $this->assertLessThan($publicShortcut, $characterGuard);
        $this->assertStringContainsString(
            "row['_reveal'] = \$reveal",
            $source
        );
    }

    public function testGmControlsEachHeroKnowledgeLevelSeparately(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Bestiary/CharacterBestiaryService.php'
        );
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertStringContainsString('setAssignment(', $service);
        $this->assertStringContainsString('setAssignments(', $service);
        $this->assertStringContainsString(
            "['unknown', 'summary', 'full']",
            $service
        );
        $this->assertStringContainsString(
            'bestiary/entries/(:num)/characters/(:num)',
            $routes
        );
        $this->assertStringContainsString(
            "put('campaigns/(:num)/bestiary/entries/(:num)/assignments'",
            $routes
        );
        $this->assertStringContainsString(
            "->where('role', 'player')",
            $service
        );
        $this->assertStringContainsString(
            "['gm', 'mg', 'game master', 'mistrz gry']",
            $service
        );
    }

    public function testAutomaticEncounterRequiresACharacterReveal(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Bestiary/'
            . 'CharacterBestiaryEncounterRecorder.php'
        );

        $this->assertStringContainsString(
            'compendium_campaign_reveals reveal_row',
            $source
        );
        $this->assertStringContainsString(
            'empty($grants[$heroId][$entryId])',
            $source
        );
        $this->assertStringContainsString(
            "'knowledge_level' => 'full'",
            $source
        );
    }
}
