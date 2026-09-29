<?php

namespace App\Services\Shop;

class ShopLegacyMapper
{
    private $itemIconResolver;
    private $consumptionCatalog;

    public function __construct()
    {
        $this->itemIconResolver = new ShopItemIconResolver();
        $this->consumptionCatalog = new \App\Services\Consumption\ConsumptionCatalog();
    }

    private function templateDetailPayload(array $template): array
    {
        $weapon = $template['weapon_json'] ?? null;
        $storedIcon = (string) ($template['img_class'] ?? $template['IMG_CLASS'] ?? '');
        $imgClass = $this->itemIconResolver->resolve($template, $storedIcon, true);
        $prize = (int) ($template['prize'] ?? 0);

        return [
            'DETAILS' => (string) ($template['details'] ?? ''),
            'ITEM_CLASS' => strtoupper((string) ($template['item_class'] ?? '')),
            'ITEM_ID' => (string) ($template['item_id'] ?? ''),
            'ITEM_GENRE' => strtoupper((string) ($template['item_genre'] ?? '')),
            'IMG_CLASS' => $imgClass,
            'ICON' => $imgClass,
            'icon' => $imgClass,
            'sprite' => $imgClass,
            'asset_id' => $imgClass,
            'PRIZE' => $prize,
            'BASE_PRICE' => $prize,
            'CURRENCY' => (string) ($template['currency_code'] ?? 'generic'),
            'CHARGE' => (int) ($template['charge'] ?? 0),
            'WEAPON' => is_array($weapon) ? $weapon : null,
            'ATTRIBUTES' => array_values((array) ($template['attributes_json'] ?? [])),
            'MECHANICS' => array_values((array) ($template['mechanics_json'] ?? [])),
            'MECHANICS_MODE' => strtoupper((string) ($template['mechanics_mode'] ?? 'EXTEND')),
            'CONSUMPTION_PROFILE_ID' => $template['consumption_profile_id'] ?? null,
        ];
    }

    public function templateToLegacy(array $template): array
    {
        return array_merge([
            'ID' => (int) $template['id'],
            'NAME' => (string) ($template['name'] ?? ''),
            'DESCRIPTION' => (string) ($template['description'] ?? ''),
            'DRAFT' => (int) ($template['draft'] ?? 0),
        ], $this->templateDetailPayload($template));
    }

    /**
     * Returns the editable consumption state for the GM warehouse only.
     * This is intentionally separate from the player-facing CONSUMPTION
     * payload, which must not disclose unidentified poisons.
     */
    public function instanceConsumptionConfiguration(array $template, array $instance): array
    {
        $resolution = $this->consumptionCatalog->resolve($template, $instance);
        $templateProfile = $this->consumptionCatalog->profile($template['consumption_profile_id'] ?? null);
        $profile = $resolution['profile'] ?? null;

        return [
            'mode' => strtolower((string) ($instance['consumption_mode'] ?? 'inherit')),
            'profileId' => $instance['consumption_profile_id'] ?? null,
            'identification' => (string) ($instance['consumption_identification'] ?? 'unknown'),
            'portions' => max(1, (int) ($instance['consumption_portions'] ?? 1)),
            'templateProfileId' => $template['consumption_profile_id'] ?? null,
            'source' => (string) ($resolution['source'] ?? 'template'),
            'resolvedProfileId' => $profile['id'] ?? null,
            'templateProfile' => $templateProfile ? $this->consumptionCatalog->adminSummary($templateProfile) : null,
            'resolvedProfile' => $profile ? $this->consumptionCatalog->adminSummary($profile) : null,
        ];
    }

    public function profileToApi(array $profile): array
    {
        return [
            'shopId' => (int) ($profile['shop_id'] ?? 0),
            'typeId' => (string) ($profile['type_id'] ?? ''),
            'signboardName' => (string) ($profile['signboard_name'] ?? ''),
            'ownerCode' => (string) ($profile['owner_code'] ?? 'BG1'),
            'ownerName' => (string) ($profile['owner_name'] ?? ''),
            'signboardAltNames' => array_values((array) ($profile['signboard_alt_names_json'] ?? [])),
            'categoryTags' => array_values((array) ($profile['category_tags_json'] ?? [])),
            'worldProfileId' => (string) ($profile['world_profile_id'] ?? 'standard'),
            'locationType' => (string) ($profile['location_type'] ?? 'miasto'),
            'legalStatus' => (string) ($profile['legal_status'] ?? 'legal'),
            'wealthTier' => (string) ($profile['wealth_tier'] ?? 'standard'),
            'reputation' => (string) ($profile['reputation'] ?? 'neutralna'),
            'seasonality' => (string) ($profile['seasonality'] ?? 'caloroczny'),
            'counterfeitRisk' => (int) ($profile['counterfeit_risk'] ?? 10),
            'pricingConfig' => ShopPricingService::normalizePricingConfig(
                $profile['pricing_config_json'] ?? null
            ),
            'marketSettings' => ShopProfileSchemaService::normalizeSettings(
                $profile['market_settings_json'] ?? null
            ),
            'marketEvents' => ShopProfileSchemaService::normalizeEvents(
                $profile['market_events_json'] ?? null
            ),
            'customPresets' => ShopProfileSchemaService::normalizePresets(
                $profile['custom_presets_json'] ?? null
            ),
            'updatedAt' => $profile['updated_at'] ?? null,
        ];
    }

    public function shopToLegacy(array $shop): array
    {
        return [
            'id' => (int) $shop['id'],
            'name' => (string) ($shop['name'] ?? ''),
            'ownerCode' => (string) ($shop['owner_code'] ?? 'BG1'),
            'ownerName' => (string) ($shop['owner_name'] ?? ''),
            'isActive' => ((int) ($shop['is_active'] ?? 0)) === 1,
            'shopEntries' => [],
            'items' => [],
            'itemIds' => [],
        ];
    }

    public function shopEntryFromTemplateRow(array $row, array $template): array
    {
        $price = $row['price_override'] !== null ? (int) $row['price_override'] : (int) ($template['prize'] ?? 0);
        $profile = $this->consumptionCatalog->profile($template['consumption_profile_id'] ?? null);
        $view = $this->consumptionCatalog->playerView($profile, 'unknown', 1, ['accessible' => false]);
        $name = $view['name'] ?? (string) ($template['name'] ?? '');

        $payload = array_merge([
            'ID' => (int) $row['template_id'],
            'INV_ID' => (int) $row['template_id'],
            'ITEM_PLACE' => 'STOISKO',
            'SLOT' => 'STOISKO',
            'PERSONAL_PSEU' => $name,
            'PERSONAL_DESC' => (string) ($template['description'] ?? ''),
            'PERSONAL_COST' => $price,
            'QUANTITY' => $row['quantity'] === null ? null : (int) $row['quantity'],
            'OWNER_OPT' => 'DEFAULT',
            'OWNER' => 'BG1',
            'NAME' => $name,
            'DESCRIPTION' => (string) ($template['description'] ?? ''),
            'ACTIVE_PRICE' => $price,
            'PRICE_OVERRIDE' => $row['price_override'],
        ], $this->templateDetailPayload($template));
        unset($payload['CONSUMPTION_PROFILE_ID']);
        return $this->protectUnidentifiedConsumptionPayload($payload, $profile, 'unknown');
    }

    public function inventoryFromTemplateRow(
        array $row,
        array $template,
        string $ownerOpt,
        string $ownerCode,
        string $itemPlace,
        ?string $trashKind = null,
        ?int $trashSourceId = null
    ): array {
        $price = $row['price_override'] !== null ? (int) $row['price_override'] : (int) ($template['prize'] ?? 0);

        $profile = $this->consumptionCatalog->profile($template['consumption_profile_id'] ?? null);
        $consumption = $this->consumptionCatalog->playerView(
            $profile,
            'unknown',
            max(1, (int) ($row['quantity'] ?? 1)),
            ['accessible' => $ownerOpt !== 'TRASH']
        );
        $name = $consumption['name'] ?? (string) ($template['name'] ?? '');
        $payload = array_merge([
            'ID' => (int) $row['id'],
            'INV_ID' => (int) $row['template_id'],
            'ITEM_PLACE' => $itemPlace,
            'SLOT' => $itemPlace,
            'PERSONAL_PSEU' => $ownerOpt === 'TRASH' ? 'Usuniety' : $name,
            'PERSONAL_DESC' => (string) ($template['description'] ?? ''),
            'PERSONAL_COST' => $price,
            'QUANTITY' => max(1, (int) ($row['quantity'] ?? 1)),
            'OWNER_OPT' => $ownerOpt,
            'OWNER' => $ownerCode,
            'NAME' => $name,
            'DESCRIPTION' => (string) ($template['description'] ?? ''),
            'ACTIVE_PRICE' => $price,
            'PRICE_OVERRIDE' => $row['price_override'],
            'TRASH_KIND' => $trashKind,
            'TRASH_SOURCE_ID' => $trashSourceId,
            'ITEM_RECORD_KIND' => 'template_stack',
            'CONSUMPTION' => $consumption,
        ], $this->templateDetailPayload($template));
        unset($payload['CONSUMPTION_PROFILE_ID']);
        return $this->protectUnidentifiedConsumptionPayload($payload, $profile, 'unknown');
    }

    public function inventoryFromInstanceRow(
        array $placement,
        array $instance,
        array $template,
        string $ownerOpt,
        string $ownerCode,
        string $itemPlace,
        ?string $trashKind = null,
        ?int $trashSourceId = null
    ): array {
        $meta = (array) ($instance['data_override_json'] ?? []);
        if (
            ($meta['LEGACY_SOURCE'] ?? '') === 'blatyrpg-old-wfrp'
            && empty($meta['ICON_OVERRIDDEN'])
        ) {
            unset($meta['IMG_CLASS'], $meta['ICON'], $meta['icon'], $meta['sprite'], $meta['asset_id']);
        }

        $resolution = $this->consumptionCatalog->resolve($template, $instance);
        $portions = $resolution['profile']
            ? max(0, (int) ($instance['consumption_portions'] ?? 1)) : 0;
        $identification = (string) ($instance['consumption_identification'] ?? 'unknown');
        $consumption = $this->consumptionCatalog->playerView(
            $resolution['profile'],
            $identification,
            $portions,
            ['accessible' => $ownerOpt !== 'TRASH']
        );
        $displayName = $consumption['name'] ?? (string) ($instance['name_override'] ?: ($template['name'] ?? ''));
        $payload = array_merge([
            'ID' => (int) $instance['id'],
            'INV_ID' => (int) $instance['template_id'],
            'ITEM_PLACE' => $itemPlace,
            'SLOT' => $itemPlace,
            'PERSONAL_PSEU' => $displayName,
            'PERSONAL_DESC' => (string) ($instance['note'] ?? ($template['description'] ?? '')),
            'PERSONAL_COST' => (int) (($placement['price_override'] ?? $template['prize']) ?? 0),
            'QUANTITY' => $portions ?: 1,
            'OWNER_OPT' => $ownerOpt,
            'OWNER' => $ownerCode,
            'NAME' => $displayName,
            'DESCRIPTION' => (string) ($instance['note'] ?: ($template['description'] ?? '')),
            'ACTIVE_PRICE' => (int) (($placement['price_override'] ?? $template['prize']) ?? 0),
            'PRICE_OVERRIDE' => $placement['price_override'],
            'TRASH_KIND' => $trashKind,
            'TRASH_SOURCE_ID' => $trashSourceId,
            'ITEM_RECORD_KIND' => 'instance',
            'CONSUMPTION' => $consumption,
        ], $this->templateDetailPayload($template), $meta, [
            'CHARGE' => (int) (($meta['CHARGE'] ?? $template['charge']) ?? 0),
            'ATTRIBUTES' => array_values((array) ($meta['ATTRIBUTES'] ?? $template['attributes_json'] ?? [])),
            'INSTANCE_META' => $meta,
        ]);

        // A placement is the authoritative source of an item's owner. Legacy
        // inventory metadata contains numeric OWNER/OWNER_OPT fields from the
        // old character sheet; merging them above must not detach the item
        // from its current character container.
        $payload['OWNER_OPT'] = $ownerOpt;
        $payload['OWNER'] = $ownerCode;
        unset($payload['CONSUMPTION_PROFILE_ID']);
        $payload = $this->protectUnidentifiedConsumptionPayload($payload, $resolution['profile'], $identification);

        $icon = $this->itemIconResolver->resolve(
            $payload,
            (string) ($payload['IMG_CLASS'] ?? $template['img_class'] ?? ''),
            true
        );
        $payload['IMG_CLASS'] = $icon;
        $payload['ICON'] = $icon;
        $payload['icon'] = $icon;
        $payload['sprite'] = $icon;
        $payload['asset_id'] = $icon;

        return $payload;
    }

    /** Keep a concealed poison's identity out of every player-facing field. */
    private function protectUnidentifiedConsumptionPayload(array $payload, ?array $profile, string $identification): array
    {
        if (!$profile || empty($profile['hiddenRisk']) || in_array($identification, ['identified', 'examined'], true)) {
            return $payload;
        }
        $description = (string) ($profile['description'] ?? '');
        $payload['PERSONAL_DESC'] = $description;
        $payload['DESCRIPTION'] = $description;
        foreach (['DETAILS', 'ITEM_ID', 'MECHANICS', 'ATTRIBUTES', 'WEAPON'] as $key) unset($payload[$key]);
        $payload['INSTANCE_META'] = [];
        return $payload;
    }

    public function worldProfileToApi(array $profile): array
    {
        return [
            'id' => (string) $profile['id'],
            'labelPl' => (string) ($profile['label_pl'] ?? ''),
            'labelEn' => (string) ($profile['label_en'] ?? ''),
            'description' => (string) ($profile['description'] ?? ''),
            'impactSummaryPl' => (string) ($profile['impact_summary_pl'] ?? ''),
            'modifiers' => (array) ($profile['modifiers_json'] ?? []),
        ];
    }

    public function catalogNodeToApi(array $node): array
    {
        $payload = (array) ($node['payload_json'] ?? []);

        return [
            'id' => (string) $node['node_key'],
            'parentId' => $node['parent_key'] !== null ? (string) $node['parent_key'] : null,
            'level' => (string) ($node['level'] ?? ''),
            'namePl' => (string) ($node['name_pl'] ?? ''),
            'nameEn' => (string) ($node['name_en'] ?? ''),
            'descriptionPl' => (string) ($node['description_pl'] ?? ''),
            'typicalLocations' => array_values((array) ($payload['typicalLocations'] ?? [])),
            'worldProfiles' => array_values((array) ($payload['worldProfiles'] ?? [])),
            'legalStatus' => (string) ($payload['legalStatus'] ?? 'legal'),
            'traits' => array_values((array) ($payload['traits'] ?? [])),
            'suggestionRules' => (array) ($payload['suggestionRules'] ?? []),
            'articleSeeds' => array_values((array) ($payload['articleSeeds'] ?? [])),
        ];
    }
}
