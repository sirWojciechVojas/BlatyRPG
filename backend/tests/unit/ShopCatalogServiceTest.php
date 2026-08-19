<?php

use App\Services\Shop\ShopCatalogService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopCatalogServiceTest extends CIUnitTestCase
{
    public function testItemDictionariesExposeLocalizedDomainLabels(): void
    {
        $dictionaries = (new ShopCatalogService())->getItemDictionaries();

        $clothing = $this->entryByCode($dictionaries['icon_categories'], 'CLOTH');
        $cloaks = $this->entryByCode($dictionaries['icon_subcategories'], 'CLOAKS');
        $tool = $this->entryByCode($dictionaries['classes'], 'TOOL');
        $twoHanded = $this->entryByCode($dictionaries['attributes'], 'TWO_HANDED');

        $this->assertSame('Ubranie', $clothing['labelPl']);
        $this->assertSame('Cloth', $clothing['labelEn']);
        $this->assertSame('Płaszcze', $cloaks['labelPl']);
        $this->assertContains('CLOTH', $cloaks['appliesTo']);
        $this->assertSame('Narzędzie', $tool['labelPl']);
        $this->assertSame('Tool', $tool['labelEn']);
        $this->assertSame('Dwuręczny', $twoHanded['labelPl']);
        $this->assertContains('WEAPON', $twoHanded['appliesTo']);
        $this->assertContains('TOOL', $twoHanded['appliesTo']);
    }

    private function entryByCode(array $entries, string $code): array
    {
        foreach ($entries as $entry) {
            if (($entry['code'] ?? null) === $code) {
                return $entry;
            }
        }

        $this->fail('Dictionary entry not found: '.$code);
    }
}
