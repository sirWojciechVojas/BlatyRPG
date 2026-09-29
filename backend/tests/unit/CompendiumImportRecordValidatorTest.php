<?php

use App\Services\Compendium\CompendiumImportRecordValidator;
use App\Services\Compendium\CompendiumSearchNormalizer;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class CompendiumImportRecordValidatorTest extends CIUnitTestCase
{
    public function testValidatesStableSourceIdRevisionAndChecksum(): void
    {
        $wikitext = "Pełna treść źródła";
        $record = [
            'id' => 'warhammerpl:42', 'title' => 'Łowca czarownic',
            'body_html' => '<p>Pełna treść źródła</p>', 'plain_text' => 'Pełna treść źródła',
            'wikitext' => $wikitext, 'sha256' => hash('sha256', $wikitext),
            'aliases' => [], 'categories' => [], 'sections' => [], 'links' => [],
            'media_references' => [], 'quality_flags' => [],
            'source' => ['page_id' => '42', 'revision_id' => '7'],
        ];

        $this->assertSame([], (new CompendiumImportRecordValidator())->validate($record));
        $record['wikitext'] .= '!';
        $this->assertContains('sha256 does not match wikitext.', (new CompendiumImportRecordValidator())->validate($record));
    }

    public function testSearchNormalizationSupportsPolishTextWithoutDiacritics(): void
    {
        $this->assertSame('lowca czarownic zolc', CompendiumSearchNormalizer::normalize('Łowca czarownic — żółć'));
    }
}
