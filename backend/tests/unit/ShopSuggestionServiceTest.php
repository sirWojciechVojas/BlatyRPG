<?php

use App\Services\Shop\ShopSuggestionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * @internal
 */
final class ShopSuggestionServiceTest extends CIUnitTestCase
{
    public function testDraftFallbackUsesItemsInsteadOfShopTypeNames(): void
    {
        $recommendations = $this->buildRecommendations([], [
            'type_id' => 'general_stall',
            'wealth_tier' => 'standard',
        ]);

        $names = array_map(static function (array $entry): string {
            return strtolower((string) ($entry['displayName'] ?? ''));
        }, $recommendations);

        $this->assertNotContains('aptekarz', $names);
        $this->assertNotContains('platnerz', $names);
        $this->assertNotContains('kram ogolny', $names);
        $this->assertTrue($this->hasName($names, 'lina konopna'));
        $this->assertTrue($this->hasName($names, 'swieca lojowa'));
    }

    public function testShopTypeChangesExistingTemplateRanking(): void
    {
        $templates = [
            [
                'id' => 1,
                'name' => 'Eliksir leczenia',
                'description' => 'Leczaca mikstura.',
                'details' => '',
                'item_class' => 'ALCHEMY',
                'item_genre' => 'POTION',
                'img_class' => 'v1089',
                'prize' => 960,
            ],
            [
                'id' => 2,
                'name' => 'Zbroja kolcza',
                'description' => 'Ciezka kolczuga.',
                'details' => '',
                'item_class' => 'ARMOR',
                'item_genre' => 'BODY',
                'img_class' => 'v0619',
                'prize' => 3600,
            ],
            [
                'id' => 3,
                'name' => 'Miecz imperialny',
                'description' => 'Klasyczna bron.',
                'details' => '',
                'item_class' => 'WEAPON',
                'item_genre' => 'MELEE',
                'img_class' => 'v0422',
                'prize' => 1920,
            ],
            [
                'id' => 4,
                'name' => 'Lina konopna',
                'description' => 'Mocna lina.',
                'details' => '',
                'item_class' => 'TOOL',
                'item_genre' => 'UTILITY',
                'img_class' => 'v1030',
                'prize' => 90,
            ],
        ];

        $apothecaryTemplates = $this->existingRecommendations($this->buildRecommendations($templates, [
            'type_id' => 'apothecary',
            'wealth_tier' => 'standard',
        ]));
        $armorerTemplates = $this->existingRecommendations($this->buildRecommendations($templates, [
            'type_id' => 'armorer',
            'wealth_tier' => 'standard',
        ]));

        $this->assertSame('Eliksir leczenia', $apothecaryTemplates[0]['displayName']);
        $this->assertContains($armorerTemplates[0]['classKey'], ['ARMOR', 'WEAPON']);
        $this->assertNotSame($apothecaryTemplates[0]['displayName'], $armorerTemplates[0]['displayName']);
    }

    public function testPolishArmorerAliasBuildsArmorDrafts(): void
    {
        $recommendations = $this->buildRecommendations([], [
            'type_id' => 'platnerz',
            'wealth_tier' => 'standard',
        ]);

        $names = array_map(static function (array $entry): string {
            return strtolower((string) ($entry['displayName'] ?? ''));
        }, $recommendations);

        $this->assertTrue($this->hasName($names, 'zbroja skorzana'));
        $this->assertTrue($this->hasName($names, 'helm zelazny'));
        $this->assertFalse($this->hasName($names, 'aptekarz'));
    }

    public function testArmorerDraftUsesShieldSpriteInsteadOfMailArmor(): void
    {
        $recommendations = $this->buildRecommendations([], [
            'type_id' => 'armorer',
            'wealth_tier' => 'standard',
        ]);
        $shield = null;
        foreach ($recommendations as $entry) {
            if (strtolower((string) ($entry['displayName'] ?? '')) === 'tarcza okuta') {
                $shield = $entry;
                break;
            }
        }

        $this->assertNotNull($shield);
        $this->assertSame('v1240', $shield['imgClass']);
        $this->assertSame('v1240', $shield['draftTemplate']['IMG_CLASS']);
        $this->assertNotSame('v0619', $shield['imgClass']);
    }

    private function buildRecommendations(array $templates, array $profile): array
    {
        $service = new ShopSuggestionService();
        $method = new ReflectionMethod($service, 'buildRecommendations');
        $method->setAccessible(true);

        return $method->invoke($service, $templates, $profile, null);
    }

    private function existingRecommendations(array $recommendations): array
    {
        return array_values(array_filter($recommendations, static function (array $entry): bool {
            return (string) ($entry['action'] ?? '') === 'use_existing';
        }));
    }

    private function hasName(array $names, string $needle): bool
    {
        foreach ($names as $name) {
            if (strpos((string) $name, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
