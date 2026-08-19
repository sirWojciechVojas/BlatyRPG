<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopItemInstanceModel;
use App\Services\Shop\AuthContextService;
use App\Services\Shop\ShopAuthorizationService;
use App\Services\Shop\ShopBootstrapService;
use App\Services\Shop\ShopCatalogService;
use App\Services\Shop\ShopContainerService;
use App\Services\Shop\ShopCurrencyService;
use App\Services\Shop\ShopLegacyMapper;
use App\Services\Shop\ShopItemMechanicsService;
use App\Services\Shop\ShopPricingService;
use App\Services\Shop\ShopProfileService;
use App\Services\Shop\ShopSuggestionService;
use App\Services\Shop\ShopTradeLedgerService;
use App\Services\Shop\ShopTradeService;

trait ShopModuleControllerPart6
{
    public function materializeSuggestion($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->suggestionService->materialize((int) $campaignId, (int) $shopId, $payload);
        return $this->response->setJSON($result);
    }

    public function rollAssortment($campaignId, $shopId)
    {
        $auth = $this->resolveAuth();
        $gmCheck = $this->authorizationService->assertGm((array) $auth, (int) $campaignId);
        if (!$gmCheck['ok']) {
            return $this->respondError($gmCheck);
        }

        $payload = $this->request->getJSON(true) ?: [];
        $result = $this->suggestionService->roll((int) $campaignId, (int) $shopId, $payload);
        if (isset($result['ok']) && !$result['ok']) {
            return $this->respondDomainError($result);
        }
        return $this->response->setJSON($result);
    }

    private function resolveAuth(): array
    {
        return $this->authContextService->resolveFromRequest($this->request);
    }

    private function respondError(array $error)
    {
        return $this->fail(
            [
                'code' => $error['code'] ?? 'forbidden',
                'message' => $error['message'] ?? 'Forbidden.',
            ],
            (int) ($error['status'] ?? 403)
        );
    }

    private function respondDomainError(array $error)
    {
        $code = (string) ($error['code'] ?? 'operation_failed');
        $status = (int) ($error['status'] ?? 400);
        $message = (string) ($error['message'] ?? $code);

        $body = array_merge($error, [
            'ok' => false,
            'code' => $code,
            'message' => $message,
        ]);
        unset($body['status']);
        return $this->response
            ->setStatusCode($status)
            ->setJSON($body);
    }

    private function replaceTemplateContainerRows(int $campaignId, int $containerId, array $entries): void
    {
        $this->containerTemplateItemModel
            ->where('campaign_id', $campaignId)
            ->where('container_id', $containerId)
            ->delete();

        $rowsByTemplate = [];
        foreach ($entries as $entry) {
            $templateId = (int) ($entry['INV_ID'] ?? 0);
            if (!$templateId) {
                continue;
            }

            $quantity = $entry['QUANTITY'] ?? 1;
            $normalizedQuantity = $quantity === null ? null : max(1, (int) $quantity);
            if (!isset($rowsByTemplate[$templateId])) {
                $rowsByTemplate[$templateId] = [
                    'quantity' => $normalizedQuantity,
                    'price_override' => isset($entry['PERSONAL_COST']) ? (int) $entry['PERSONAL_COST'] : null,
                ];
                continue;
            }

            if ($rowsByTemplate[$templateId]['quantity'] === null || $normalizedQuantity === null) {
                $rowsByTemplate[$templateId]['quantity'] = null;
            } else {
                $rowsByTemplate[$templateId]['quantity'] += $normalizedQuantity;
            }
            if (isset($entry['PERSONAL_COST'])) {
                $rowsByTemplate[$templateId]['price_override'] = (int) $entry['PERSONAL_COST'];
            }
        }

        foreach ($rowsByTemplate as $templateId => $row) {
            $this->containerTemplateItemModel->insert([
                'campaign_id' => $campaignId,
                'container_id' => $containerId,
                'template_id' => $templateId,
                'quantity' => $row['quantity'],
                'price_override' => $row['price_override'],
            ]);
        }
    }

    private function legacyTemplateInputToRecord(int $campaignId, array $input, array $existing = []): array
    {
        $currencyCode = strtolower(trim((string) (
            $input['CURRENCY'] ?? ($existing['currency_code'] ?? 'generic')
        )));
        if ($currencyCode === '' || $currencyCode === 'generic') {
            $currencyCode = (string) (
                $this->currencyService->getCampaignCurrencyContext($campaignId)['defaultCurrencyCode'] ?? 'generic'
            );
        }
        $record = [
            'campaign_id' => $campaignId,
            'name' => (string) ($input['NAME'] ?? ($existing['name'] ?? '')),
            'description' => (string) ($input['DESCRIPTION'] ?? ($existing['description'] ?? '')),
            'details' => (string) ($input['DETAILS'] ?? ($existing['details'] ?? '')),
            'item_class' => strtoupper((string) ($input['ITEM_CLASS'] ?? ($existing['item_class'] ?? 'TOOL'))),
            'item_id' => (string) ($input['ITEM_ID'] ?? ($existing['item_id'] ?? '')),
            'item_genre' => strtoupper((string) ($input['ITEM_GENRE'] ?? ($existing['item_genre'] ?? 'UTILITY'))),
            'img_class' => (string) ($input['IMG_CLASS'] ?? ($existing['img_class'] ?? 'v0001')),
            'prize' => (int) ($input['PRIZE'] ?? ($existing['prize'] ?? 0)),
            'currency_code' => $currencyCode,
            'charge' => (int) ($input['CHARGE'] ?? ($existing['charge'] ?? 0)),
            'draft' => (int) ($input['DRAFT'] ?? ($existing['draft'] ?? 0)),
            'weapon_json' => array_key_exists('WEAPON', $input) ? (array) $input['WEAPON'] : ($existing['weapon_json'] ?? null),
            'attributes_json' => $this->normalizeAttributeCodes(
                $input['ATTRIBUTES'] ?? ($existing['attributes_json'] ?? [])
            ),
            'mechanics_json' => (new ShopItemMechanicsService())->normalizeMechanics(
                $input['MECHANICS'] ?? ($existing['mechanics_json'] ?? [])
            ),
            'mechanics_mode' => (new ShopItemMechanicsService())->normalizeMode(
                $input['MECHANICS_MODE'] ?? ($existing['mechanics_mode'] ?? 'EXTEND')
            ),
        ];
        $record['img_class'] = $this->itemIconResolver->resolve(
            $record,
            (string) $record['img_class'],
            array_key_exists('IMG_CLASS', $input)
        );
        return $record;
    }

    private function normalizeAttributeCodes($values): array
    {
        return array_values(array_unique(array_filter(array_map(
            static function ($value): string {
                return strtoupper(trim((string) $value));
            },
            (array) $values
        ))));
    }
}
