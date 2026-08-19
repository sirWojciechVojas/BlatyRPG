<?php

namespace App\Services\Shop;

use App\Models\ShopModel;
use App\Models\ShopProfileModel;
use App\Models\ShopProfileRevisionModel;

class ShopProfileService
{
    private $shopModel;
    private $profileModel;
    private $mapper;
    private $currencyService;
    private $revisionModel;

    public function __construct()
    {
        $this->shopModel = new ShopModel();
        $this->profileModel = new ShopProfileModel();
        $this->mapper = new ShopLegacyMapper();
        $this->currencyService = new ShopCurrencyService();
        $this->revisionModel = new ShopProfileRevisionModel();
    }

    public function getProfile(int $campaignId, int $shopId): ?array
    {
        $profile = $this->profileModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        if (!$profile) {
            $shop = $this->shopModel
                ->where('campaign_id', $campaignId)
                ->where('id', $shopId)
                ->first();

            if (!$shop) {
                return null;
            }

            $profile = $this->defaultProfileRecord($campaignId, $shop);
        }

        return $this->profileWithCampaignCurrency(
            $this->mapper->profileToApi($profile),
            $campaignId
        );
    }

    public function upsertProfile(
        int $campaignId,
        int $shopId,
        array $payload,
        ?int $changedBy = null,
        string $changeType = 'update'
    ): ?array
    {
        $shop = $this->shopModel
            ->where('campaign_id', $campaignId)
            ->where('id', $shopId)
            ->first();

        if (!$shop) {
            return null;
        }

        $existing = $this->profileModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->first();

        $pricingConfig = ShopPricingService::normalizePricingConfig(
            $payload['pricingConfig'] ?? ($existing['pricing_config_json'] ?? null)
        );
        $currencyContext = $this->currencyService->getCampaignCurrencyContext($campaignId);
        $settlementCurrencyCode = strtolower(trim((string) (
            $pricingConfig['currencyPolicy']['settlementCurrencyCode'] ?? ''
        )));
        $defaultCurrencyCode = (string) ($currencyContext['defaultCurrencyCode'] ?? 'generic');
        if (
            $settlementCurrencyCode === '' ||
            ($settlementCurrencyCode === 'generic' && $defaultCurrencyCode !== 'generic')
        ) {
            $pricingConfig['currencyPolicy']['settlementCurrencyCode'] = $defaultCurrencyCode;
        }

        $record = [
            'campaign_id' => $campaignId,
            'shop_id' => $shopId,
            'type_id' => (string) ($payload['typeId'] ?? ($existing['type_id'] ?? '')),
            'signboard_name' => (string) ($payload['signboardName'] ?? ($existing['signboard_name'] ?? $shop['name'])),
            'owner_code' => strtoupper((string) ($payload['ownerCode'] ?? ($existing['owner_code'] ?? $shop['owner_code']))),
            'owner_name' => (string) ($payload['ownerName'] ?? ($existing['owner_name'] ?? $shop['owner_name'])),
            'signboard_alt_names_json' => array_values((array) ($payload['signboardAltNames'] ?? ($existing['signboard_alt_names_json'] ?? []))),
            'category_tags_json' => array_values((array) ($payload['categoryTags'] ?? ($existing['category_tags_json'] ?? []))),
            'world_profile_id' => (string) ($payload['worldProfileId'] ?? ($existing['world_profile_id'] ?? 'standard')),
            'location_type' => (string) ($payload['locationType'] ?? ($existing['location_type'] ?? 'miasto')),
            'legal_status' => (string) ($payload['legalStatus'] ?? ($existing['legal_status'] ?? 'legal')),
            'wealth_tier' => (string) ($payload['wealthTier'] ?? ($existing['wealth_tier'] ?? 'standard')),
            'reputation' => (string) ($payload['reputation'] ?? ($existing['reputation'] ?? 'neutralna')),
            'seasonality' => (string) ($payload['seasonality'] ?? ($existing['seasonality'] ?? 'caloroczny')),
            'counterfeit_risk' => (int) ($payload['counterfeitRisk'] ?? ($existing['counterfeit_risk'] ?? 10)),
            'pricing_config_json' => $pricingConfig,
            'market_settings_json' => ShopProfileSchemaService::normalizeSettings(
                $payload['marketSettings'] ?? ($existing['market_settings_json'] ?? null)
            ),
            'market_events_json' => ShopProfileSchemaService::normalizeEvents(
                $payload['marketEvents'] ?? ($existing['market_events_json'] ?? null)
            ),
            'custom_presets_json' => ShopProfileSchemaService::normalizePresets(
                $payload['customPresets'] ?? ($existing['custom_presets_json'] ?? null)
            ),
        ];

        if ($existing) {
            $this->profileModel->update((int) $existing['id'], $record);
            $saved = $this->profileModel->find((int) $existing['id']);
        } else {
            $this->profileModel->insert($record);
            $saved = $this->profileModel->find((int) $this->profileModel->getInsertID());
        }

        $this->shopModel->update($shopId, [
            'name' => $record['signboard_name'],
            'owner_code' => $record['owner_code'],
            'owner_name' => $record['owner_name'],
        ]);

        if (!$saved) {
            return null;
        }
        $apiProfile = $this->profileWithCampaignCurrency($this->mapper->profileToApi($saved), $campaignId);
        $before = $existing ? $this->mapper->profileToApi($existing) : null;
        if (!$before || $this->snapshotHash($before) !== $this->snapshotHash($apiProfile)) {
            $this->revisionModel->insert([
                'campaign_id' => $campaignId,
                'shop_id' => $shopId,
                'changed_by' => $changedBy,
                'change_type' => substr($changeType, 0, 32),
                'snapshot_json' => $apiProfile,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        return $apiProfile;
    }

    public function getProfilesMap(int $campaignId): array
    {
        $profiles = $this->profileModel
            ->where('campaign_id', $campaignId)
            ->findAll();

        $result = [];
        foreach ($profiles as $profile) {
            $result[(int) $profile['shop_id']] = $this->profileWithCampaignCurrency(
                $this->mapper->profileToApi($profile),
                $campaignId
            );
        }

        return $result;
    }

    private function defaultProfileRecord(int $campaignId, array $shop): array
    {
        $defaultPricing = ShopPricingService::defaultPricingConfig();
        $currencyContext = $this->currencyService->getCampaignCurrencyContext($campaignId);
        if (in_array((string) ($currencyContext['systemCode'] ?? ''), ['wfrp2ed', 'wfrp', 'warhammer'], true)) {
            $defaultPricing['currencyPolicy']['exchangeRates'] = [
                'wfrp_empire' => 1.0,
                'wfrp_bretonnia' => 1.0,
            ];
        }
        return [
            'campaign_id' => $campaignId,
            'shop_id' => (int) $shop['id'],
            'type_id' => '',
            'signboard_name' => (string) $shop['name'],
            'owner_code' => (string) $shop['owner_code'],
            'owner_name' => (string) $shop['owner_name'],
            'signboard_alt_names_json' => [],
            'category_tags_json' => [],
            'world_profile_id' => 'standard',
            'location_type' => 'miasto',
            'legal_status' => 'legal',
            'wealth_tier' => 'standard',
            'reputation' => 'neutralna',
            'seasonality' => 'caloroczny',
            'counterfeit_risk' => 10,
            'pricing_config_json' => $this->profileWithCampaignCurrency([
                'pricingConfig' => $defaultPricing,
            ], $campaignId)['pricingConfig'],
            'market_settings_json' => ShopProfileSchemaService::defaultMarketSettings(),
            'market_events_json' => [],
            'custom_presets_json' => ['profiles' => [], 'policies' => []],
        ];
    }

    private function profileWithCampaignCurrency(array $profile, int $campaignId): array
    {
        $config = ShopPricingService::normalizePricingConfig($profile['pricingConfig'] ?? null);
        $context = $this->currencyService->getCampaignCurrencyContext($campaignId);
        $settlementCurrencyCode = strtolower(trim((string) (
            $config['currencyPolicy']['settlementCurrencyCode'] ?? ''
        )));
        $defaultCurrencyCode = (string) ($context['defaultCurrencyCode'] ?? 'generic');
        if (
            $settlementCurrencyCode === '' ||
            ($settlementCurrencyCode === 'generic' && $defaultCurrencyCode !== 'generic')
        ) {
            $config['currencyPolicy']['settlementCurrencyCode'] = (string) (
                $defaultCurrencyCode
            );
        }
        $profile['pricingConfig'] = $config;
        return $profile;
    }

    public function validate(array $payload): array
    {
        return ShopProfileSchemaService::validate($payload);
    }

    public function history(int $campaignId, int $shopId, int $limit = 30): array
    {
        $rows = $this->revisionModel
            ->where('campaign_id', $campaignId)
            ->where('shop_id', $shopId)
            ->orderBy('id', 'DESC')
            ->findAll(max(1, min(100, $limit)));
        return array_map(static function (array $row): array {
            $snapshot = (array) ($row['snapshot_json'] ?? []);
            return [
                'id' => (int) $row['id'],
                'changeType' => (string) ($row['change_type'] ?? 'update'),
                'changedBy' => isset($row['changed_by']) ? (int) $row['changed_by'] : null,
                'createdAt' => $row['created_at'] ?? null,
                'summary' => [
                    'name' => (string) ($snapshot['signboardName'] ?? ''),
                    'typeId' => (string) ($snapshot['typeId'] ?? ''),
                    'policyId' => $snapshot['pricingConfig']['policyId'] ?? null,
                ],
                'snapshot' => $snapshot,
            ];
        }, $rows);
    }

    public function exportPortable(int $campaignId, int $shopId): ?array
    {
        $profile = $this->getProfile($campaignId, $shopId);
        return $profile ? ShopProfileSchemaService::portableProfile($profile) : null;
    }

    public function importPortable(
        int $campaignId,
        int $shopId,
        array $document,
        ?int $changedBy = null
    ): ?array {
        if (($document['schema'] ?? '') !== 'blatyrpg.shop-profile') {
            return null;
        }
        $current = $this->getProfile($campaignId, $shopId);
        if (!$current) return null;
        $portable = array_merge((array) ($document['profile'] ?? []), [
            'marketSettings' => $document['marketSettings'] ?? [],
            'marketEvents' => $document['marketEvents'] ?? [],
            'customPresets' => $document['customPresets'] ?? [],
        ]);
        if ($this->validate($portable)) return null;
        $clean = ShopProfileSchemaService::portableProfile($portable);
        $portable = array_merge((array) $clean['profile'], [
            'marketSettings' => $clean['marketSettings'],
            'marketEvents' => $clean['marketEvents'],
            'customPresets' => $clean['customPresets'],
        ]);
        return $this->upsertProfile(
            $campaignId,
            $shopId,
            array_merge($current, $portable),
            $changedBy,
            'import'
        );
    }

    private function snapshotHash(array $profile): string
    {
        unset($profile['updatedAt']);
        return md5((string) json_encode($profile, JSON_UNESCAPED_UNICODE));
    }
}
