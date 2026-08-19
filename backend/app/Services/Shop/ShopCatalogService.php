<?php

namespace App\Services\Shop;

use App\Models\ShopCatalogNodeModel;
use App\Models\ShopIconMetadataModel;
use App\Models\ShopItemDictionaryEntryModel;
use App\Models\ShopTypeModel;
use App\Models\ShopWorldProfileModel;

class ShopCatalogService
{
    private $catalogNodeModel;
    private $shopTypeModel;
    private $worldProfileModel;
    private $itemDictionaryModel;
    private $iconMetadataModel;
    private $itemMechanicsService;
    private $mapper;
    private $shopTypesCache;
    private $catalogNetworkCache;
    private $worldProfilesCache;
    private $dictionaryCache = [];

    public function __construct()
    {
        $this->catalogNodeModel = new ShopCatalogNodeModel();
        $this->shopTypeModel = new ShopTypeModel();
        $this->worldProfileModel = new ShopWorldProfileModel();
        $this->itemDictionaryModel = new ShopItemDictionaryEntryModel();
        $this->iconMetadataModel = new ShopIconMetadataModel();
        $this->itemMechanicsService = new ShopItemMechanicsService();
        $this->mapper = new ShopLegacyMapper();
    }

    public function getCatalogNetwork(): array
    {
        if ($this->catalogNetworkCache !== null) {
            return $this->catalogNetworkCache;
        }
        $nodes = $this->catalogNodeModel
            ->orderBy('id', 'ASC')
            ->findAll();

        $catalogNodes = array_map(function (array $row): array {
            return $this->mapper->catalogNodeToApi($row);
        }, $nodes);

        $knownIds = [];
        foreach ($catalogNodes as $node) {
            $knownIds[(string) ($node['id'] ?? '')] = true;
        }

        foreach ($this->getShopTypes() as $type) {
            if (isset($knownIds[$type['slug']])) {
                continue;
            }

            $catalogNodes[] = [
                'id' => $type['slug'],
                'parentId' => null,
                'level' => 'type',
                'namePl' => $type['name'],
                'nameEn' => null,
                'descriptionPl' => $type['description'],
                'category' => $type['category'],
                'sortOrder' => $type['sortOrder'],
                'isActive' => $type['isActive'],
                'typicalLocations' => [],
                'worldProfiles' => [],
                'legalStatus' => 'legal',
                'traits' => [],
                'suggestionRules' => ['requiredItemClasses' => []],
                'articleSeeds' => [],
            ];
        }

        return $this->catalogNetworkCache = $catalogNodes;
    }

    public function getShopTypes(): array
    {
        if ($this->shopTypesCache !== null) {
            return $this->shopTypesCache;
        }
        $types = $this->shopTypeModel
            ->where('is_active', 1)
            ->orderBy('sort_order', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->shopTypesCache = array_map(function (array $row): array {
            return [
                'id' => (int) ($row['id'] ?? 0),
                'slug' => (string) ($row['slug'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'category' => (string) ($row['category'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'isActive' => ((int) ($row['is_active'] ?? 0)) === 1,
                'sortOrder' => (int) ($row['sort_order'] ?? 0),
            ];
        }, $types);
    }

    public function getWorldProfiles(): array
    {
        if ($this->worldProfilesCache !== null) {
            return $this->worldProfilesCache;
        }
        $profiles = $this->worldProfileModel
            ->orderBy('id', 'ASC')
            ->findAll();

        return $this->worldProfilesCache = array_map(function (array $row): array {
            return $this->mapper->worldProfileToApi($row);
        }, $profiles);
    }

    public function getItemDictionaries(?int $campaignId = null): array
    {
        $defaults = ShopItemDictionaryDefaults::all();
        if (!$campaignId) {
            return $defaults;
        }
        if (isset($this->dictionaryCache[$campaignId])) {
            return $this->dictionaryCache[$campaignId];
        }

        $rows = $this->ensureItemDictionaries($campaignId, $defaults);
        $result = array_fill_keys(array_keys($defaults), []);
        usort($rows, static function (array $left, array $right): int {
            return [$left['group_code'], $left['sort_order'], $left['code']]
                <=> [$right['group_code'], $right['sort_order'], $right['code']];
        });
        foreach ($rows as $row) {
            if ((int) ($row['is_active'] ?? 0) !== 1) {
                continue;
            }
            $group = (string) ($row['group_code'] ?? '');
            if (!array_key_exists($group, $result)) {
                continue;
            }
            $result[$group][] = [
                'id' => (int) $row['id'],
                'code' => (string) $row['code'],
                'labelPl' => (string) $row['label_pl'],
                'labelEn' => (string) $row['label_en'],
                'appliesTo' => array_values((array) ($row['applies_to_json'] ?? [])),
                'mechanics' => $this->itemMechanicsService->normalizeMechanics(
                    $row['mechanics_json'] ?? []
                ),
                'sortOrder' => (int) ($row['sort_order'] ?? 0),
            ];
        }

        return $this->dictionaryCache[$campaignId] = $result;
    }

    public function saveItemDictionaryEntry(int $campaignId, array $input, ?int $entryId = null): ?array
    {
        $group = strtolower(trim((string) ($input['group'] ?? $input['groupCode'] ?? '')));
        $code = strtoupper(trim((string) ($input['code'] ?? '')));
        if (!array_key_exists($group, ShopItemDictionaryDefaults::all()) || !preg_match('/^[A-Z][A-Z0-9_]{1,63}$/', $code)) {
            return null;
        }
        $record = [
            'campaign_id' => $campaignId,
            'group_code' => $group,
            'code' => $code,
            'label_pl' => trim((string) ($input['labelPl'] ?? $code)),
            'label_en' => trim((string) ($input['labelEn'] ?? $code)),
            'applies_to_json' => array_values(array_unique(array_filter(array_map('strtoupper', (array) ($input['appliesTo'] ?? []))))),
            'mechanics_json' => $this->itemMechanicsService->normalizeMechanics(
                $input['mechanics'] ?? []
            ),
            'is_active' => 1,
            'sort_order' => (int) ($input['sortOrder'] ?? 0),
        ];
        if ($entryId) {
            $existing = $this->itemDictionaryModel
                ->where('campaign_id', $campaignId)
                ->where('id', $entryId)
                ->first();
            if (!$existing) {
                return null;
            }
            $existingGroup = (string) ($existing['group_code'] ?? '');
            $existingCode = (string) ($existing['code'] ?? '');
            if ($existingGroup !== $group) {
                return null;
            }
            if ($existingCode !== $code) {
                $collision = $this->itemDictionaryModel
                    ->where('campaign_id', $campaignId)
                    ->where('group_code', $group)
                    ->where('code', $code)
                    ->first();
                if ($collision && (int) ($collision['id'] ?? 0) !== $entryId) {
                    return null;
                }
                $renamedFrom = array_values(array_filter(
                    (array) ($existing['applies_to_json'] ?? []),
                    static fn ($value): bool => str_starts_with(
                        strtoupper((string) $value),
                        'RENAMED_FROM:'
                    )
                ));
                $record['applies_to_json'] = array_values(array_unique([
                    ...$record['applies_to_json'],
                    ...$renamedFrom,
                    'RENAMED_FROM:'.$existingCode,
                ]));

                $database = db_connect();
                $database->transStart();
                $this->itemDictionaryModel->update($entryId, $record);
                $this->itemDictionaryModel->insert([
                    'campaign_id' => $campaignId,
                    'group_code' => $existingGroup,
                    'code' => $existingCode,
                    'label_pl' => (string) ($existing['label_pl'] ?? $existingCode),
                    'label_en' => (string) ($existing['label_en'] ?? $existingCode),
                    'applies_to_json' => (array) ($existing['applies_to_json'] ?? []),
                    'mechanics_json' => (array) ($existing['mechanics_json'] ?? []),
                    'is_active' => 0,
                    'sort_order' => (int) ($existing['sort_order'] ?? 0),
                ]);
                $this->replaceDictionaryCodeReferences(
                    $campaignId,
                    $group,
                    $existingCode,
                    $code,
                    $entryId
                );
                $this->replaceIconMetadataCode(
                    $campaignId,
                    $group,
                    $existingCode,
                    $code
                );
                $database->transComplete();
                if (!$database->transStatus()) {
                    return null;
                }
            } else {
                $this->itemDictionaryModel->update($entryId, $record);
            }
            unset($this->dictionaryCache[$campaignId]);
            return $this->itemDictionaryModel->find($entryId);
        }
        $existing = $this->itemDictionaryModel
            ->where('campaign_id', $campaignId)
            ->where('group_code', $group)
            ->where('code', $code)
            ->first();
        if ($existing) {
            if ((int) ($existing['is_active'] ?? 0) === 1) {
                return null;
            }
            $this->itemDictionaryModel->update((int) $existing['id'], $record);
            unset($this->dictionaryCache[$campaignId]);
            return $this->itemDictionaryModel->find((int) $existing['id']);
        }
        $this->itemDictionaryModel->insert($record);
        unset($this->dictionaryCache[$campaignId]);
        return $this->itemDictionaryModel->find((int) $this->itemDictionaryModel->getInsertID());
    }

    public function archiveItemDictionaryEntry(int $campaignId, int $entryId): bool
    {
        $existing = $this->itemDictionaryModel
            ->where('campaign_id', $campaignId)
            ->where('id', $entryId)
            ->first();
        $archived = $existing
            ? $this->itemDictionaryModel->update($entryId, ['is_active' => 0])
            : false;
        if ($archived) {
            unset($this->dictionaryCache[$campaignId]);
        }
        return $archived;
    }

    private function ensureItemDictionaries(int $campaignId, array $defaults): array
    {
        $rows = $this->itemDictionaryModel
            ->where('campaign_id', $campaignId)
            ->findAll();
        $known = [];
        foreach ($rows as $row) {
            $known[$row['group_code'].'|'.$row['code']] = true;
        }
        $missing = [];
        foreach ($defaults as $group => $entries) {
            foreach (array_values($entries) as $index => $entry) {
                if (isset($known[$group.'|'.$entry['code']])) {
                    continue;
                }
                $missing[] = [
                    'campaign_id' => $campaignId,
                    'group_code' => $group,
                    'code' => $entry['code'],
                    'label_pl' => $entry['labelPl'],
                    'label_en' => $entry['labelEn'],
                    'applies_to_json' => json_encode($entry['appliesTo'], JSON_UNESCAPED_UNICODE),
                    'mechanics_json' => json_encode(
                        $this->itemMechanicsService->normalizeMechanics(
                            $entry['mechanics'] ?? []
                        ),
                        JSON_UNESCAPED_UNICODE
                    ),
                    'is_active' => 1,
                    'sort_order' => $index,
                ];
            }
        }
        if (!$missing) {
            return $rows;
        }
        $this->itemDictionaryModel->insertBatch($missing);
        return $this->itemDictionaryModel
            ->where('campaign_id', $campaignId)
            ->findAll();
    }

    private function replaceDictionaryCodeReferences(
        int $campaignId,
        string $sourceGroup,
        string $oldCode,
        string $newCode,
        int $editedEntryId
    ): void {
        $targetGroups = [
            'icon_categories' => ['icon_subcategories'],
            'icon_subcategories' => [],
            'classes' => ['icon_categories', 'icon_subcategories', 'genres', 'attributes'],
            'genres' => ['icon_categories', 'icon_subcategories'],
            'attributes' => [],
        ][$sourceGroup] ?? [];
        if (!$targetGroups) {
            return;
        }
        $prefix = [
            'icon_categories' => 'CATEGORY:',
            'icon_subcategories' => 'SUBCATEGORY:',
            'classes' => 'CLASS:',
            'genres' => 'GENRE:',
            'attributes' => 'ATTRIBUTE:',
        ][$sourceGroup] ?? '';

        $rows = $this->itemDictionaryModel
            ->where('campaign_id', $campaignId)
            ->findAll();
        foreach ($rows as $row) {
            if (
                (int) ($row['id'] ?? 0) === $editedEntryId
                || !in_array((string) ($row['group_code'] ?? ''), $targetGroups, true)
            ) {
                continue;
            }
            $relations = array_values((array) ($row['applies_to_json'] ?? []));
            $replaced = array_map(
                static function ($value) use ($oldCode, $newCode, $prefix): string {
                    $normalized = strtoupper(trim((string) $value));
                    if ($normalized === $oldCode) {
                        return $newCode;
                    }
                    if ($prefix !== '' && $normalized === $prefix.$oldCode) {
                        return $prefix.$newCode;
                    }
                    return $normalized;
                },
                $relations
            );
            if ($replaced !== $relations) {
                $this->itemDictionaryModel->update(
                    (int) $row['id'],
                    ['applies_to_json' => array_values(array_unique($replaced))]
                );
            }
        }
    }

    private function replaceIconMetadataCode(
        int $campaignId,
        string $group,
        string $oldCode,
        string $newCode
    ): void {
        $field = [
            'icon_categories' => 'type_keys_json',
            'icon_subcategories' => 'subtype_keys_json',
            'classes' => 'item_classes_json',
            'genres' => 'item_genres_json',
        ][$group] ?? null;
        if (!$field) {
            return;
        }
        $rows = $this->iconMetadataModel
            ->where('campaign_id', $campaignId)
            ->findAll();
        foreach ($rows as $row) {
            $values = array_values((array) ($row[$field] ?? []));
            if (!in_array($oldCode, $values, true)) {
                continue;
            }
            $this->iconMetadataModel->update(
                (int) $row['id'],
                [
                    $field => array_values(array_unique(array_map(
                        static fn ($value): string => (string) $value === $oldCode
                            ? $newCode
                            : (string) $value,
                        $values
                    ))),
                ]
            );
        }
    }
}
