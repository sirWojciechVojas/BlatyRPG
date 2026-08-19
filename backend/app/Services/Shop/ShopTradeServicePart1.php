<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

trait ShopTradeServicePart1
{
    public function __construct()
    {
        $this->db = \Config\Database::connect();
        $this->containerTemplateItemModel = new ShopContainerTemplateItemModel();
        $this->containerInstanceItemModel = new ShopContainerInstanceItemModel();
        $this->walletService = new ShopWalletService();
        $this->templateModel = new ShopTemplateModel();
        $this->transactionModel = new ShopTradeTransactionModel();
        $this->shopModel = new ShopModel();
        $this->containerService = new ShopContainerService();
        $this->profileService = new ShopProfileService();
        $this->pricingService = new ShopPricingService();
        $this->snapshotService = new ShopTradeSnapshotService();
        $this->paymentService = new ShopPaymentService();
    }

    public function buy(int $campaignId, array $payload, array $authContext, ?string $idempotencyKey = null): array
    {
        return $this->tradeInstances($campaignId, $payload, $authContext, $idempotencyKey, 'BUY');
    }

    public function sell(int $campaignId, array $payload, array $authContext, ?string $idempotencyKey = null): array
    {
        return $this->tradeInstances($campaignId, $payload, $authContext, $idempotencyKey, 'SELL');
    }

    public function quoteBuyPayment(int $campaignId, array $payload, array $authContext): array
    {
        return $this->tradeInstances($campaignId, $payload, $authContext, null, 'QUOTE_BUY');
    }
}
