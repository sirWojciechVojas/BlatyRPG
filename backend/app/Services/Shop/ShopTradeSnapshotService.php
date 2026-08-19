<?php

namespace App\Services\Shop;

use App\Models\ShopContainerModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;

class ShopTradeSnapshotService
{
    private $db;
    private $containerModel;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $walletService;

    use ShopTradeSnapshotServicePart1,
        ShopTradeSnapshotServicePart2;
}
