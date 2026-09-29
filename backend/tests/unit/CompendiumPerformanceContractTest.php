<?php

namespace Tests\Unit;

use CodeIgniter\Test\CIUnitTestCase;

final class CompendiumPerformanceContractTest extends CIUnitTestCase
{
    public function testListLoadsTagsForTheWholePageInOneQuery(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );

        $this->assertStringContainsString('versionTagsMap(', $source);
        $this->assertStringContainsString('?array $knownTags = null', $source);
        $this->assertStringContainsString(
            "->whereIn('vt.version_id', \$versionIds)",
            $source
        );
    }

    public function testOverviewAggregatesDepartmentCountsInTheDatabase(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );

        $this->assertStringContainsString('departmentCounts(', $source);
        $this->assertStringContainsString(
            "'SUM(CASE WHEN ('",
            $source
        );
        $this->assertStringNotContainsString(
            'foreach ($entities as $entity)',
            $source
        );
        $this->assertStringContainsString(
            "'characters' => ['character', 'person']",
            $source
        );
        $this->assertStringContainsString('AS generic_npc_count', $source);
        $this->assertStringContainsString("'generic' => \$genericNpcs", $source);
    }

    public function testNpcListCanSeparateNamedAndGenericCharacters(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );
        $corpus = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );

        $this->assertStringContainsString("\$query['npcKind']", $service);
        $this->assertStringContainsString("LIKE '%generyczni%'", $service);
        $this->assertStringContainsString("'npcKind' => \$this->npcKind", $corpus);
    }

    public function testListRowsExposeTheirCompendiumSource(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );
        $corpus = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );

        $this->assertStringContainsString(
            'cs.name AS corpus_source_name',
            $service
        );
        $this->assertStringContainsString(
            "'sourceName' => \$row['corpus_source_name'] ?? null",
            $corpus
        );
    }

    public function testListQueryDoesNotReadUnusedArticleBodies(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );

        $this->assertStringNotContainsString("->select('e.*, v.*", $source);
        $this->assertStringContainsString(
            'v.id, v.version_number, v.type_id, v.parent_entry_id, v.title',
            $source
        );
        $this->assertStringNotContainsString(
            'ce.search_text_normalized AS corpus_search_text_normalized',
            $source
        );
        $this->assertStringContainsString('entityCategoryNamesMap(', $source);
        $this->assertStringContainsString(
            "->whereIn('ec.entity_id', \$entityIds)",
            $source
        );
    }

    public function testSearchUsesFullTextCandidatesAndRelevanceOrdering(): void
    {
        $source = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );

        $this->assertStringContainsString('rankedSearchEntryIds(', $source);
        $this->assertStringContainsString(
            'MATCH(ce.normalized_name,ce.aliases_normalized,ce.search_text_normalized)',
            $source
        );
        $this->assertStringContainsString('AGAINST (? IN BOOLEAN MODE)', $source);
        $this->assertStringContainsString(
            "->like('ce.normalized_name', \$normalized, 'after')",
            $source
        );
        $this->assertStringContainsString("->whereIn('e.id', \$rankedSearchIds)", $source);
        $this->assertStringContainsString("'FIELD(e.id,'", $source);
    }

    public function testDetailReusesLoadedEntityAndCurrentRevision(): void
    {
        $service = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumService.php'
        );
        $corpus = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );

        $this->assertStringContainsString('?array $knownEntity = null', $service);
        $this->assertStringContainsString(
            '$knownEntity ?: $this->corpus->entityForEntry',
            $service
        );
        $this->assertStringContainsString(
            "'id' => (int) \$entity['current_source_revision_id']",
            $corpus
        );
        $this->assertStringNotContainsString("->select('ce.*", $corpus);
        $this->assertStringNotContainsString('sr.plain_text', $corpus);
        $this->assertStringContainsString('readableEntityIds(', $corpus);
        $this->assertStringContainsString("->whereIn('entity_id', \$pending)", $corpus);
    }

    public function testReadTelemetryIsOutsideTheArticleGetPath(): void
    {
        $corpus = file_get_contents(
            APPPATH . 'Services/Compendium/CompendiumCorpusService.php'
        );
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $detailStart = strpos($corpus, 'public function decorateDetail');
        $overviewStart = strpos($corpus, 'public function overview');
        $detail = substr($corpus, $detailStart, $overviewStart - $detailStart);
        $this->assertStringNotContainsString('recordRead(', $detail);
        $this->assertStringContainsString('/read', $routes);
        $this->assertStringContainsString(
            'ON DUPLICATE KEY UPDATE',
            $corpus
        );
    }

    public function testMigrationAddsOverviewIndexes(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/'
            . '2026-09-22-100000_OptimizeCompendiumReads.php'
        );

        $this->assertStringContainsString(
            'idx_compendium_entities_overview',
            $source
        );
        $this->assertStringContainsString(
            'idx_compendium_activity_overview',
            $source
        );
    }

    public function testMigrationAddsListOrderingIndexes(): void
    {
        $source = file_get_contents(
            APPPATH . 'Database/Migrations/'
            . '2026-09-27-110000_OptimizeCompendiumListOrdering.php'
        );

        $this->assertStringContainsString(
            'idx_compendium_entries_active_list',
            $source
        );
        $this->assertStringContainsString(
            'idx_compendium_versions_list_title',
            $source
        );
        $this->assertStringContainsString(
            'idx_compendium_versions_list_timeline',
            $source
        );
    }
}
