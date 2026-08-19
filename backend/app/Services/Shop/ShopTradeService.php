<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;
use App\Models\ShopModel;

class ShopTradeService
{
    private $db;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $walletService;
    private $templateModel;
    private $transactionModel;
    private $shopModel;
    private $containerService;
    private $profileService;
    private $pricingService;
    private $snapshotService;
    private $paymentService;

    use ShopTradeServicePart1,
        ShopTradeServicePart2,
        ShopTradeServicePart3,
        ShopTradeServicePart4,
        ShopTradeServicePart5,
        ShopTradeServicePart6;
}
