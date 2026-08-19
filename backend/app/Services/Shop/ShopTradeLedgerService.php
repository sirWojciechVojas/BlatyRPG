<?php

namespace App\Services\Shop;

use App\Models\ShopModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTradeTransactionModel;

class ShopTradeLedgerService
{
    private $db;
    private $transactionModel;
    private $shopModel;
    private $templateModel;
    private $snapshotService;
    private $tradeService;

    use ShopTradeLedgerServicePart1,
        ShopTradeLedgerServicePart2,
        ShopTradeLedgerServicePart3;
}
