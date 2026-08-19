<?php

namespace App\Services\Shop;

use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopModel;
use App\Models\ShopTemplateModel;

class ShopContainerService
{
    private $db;
    private $containerModel;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $instanceModel;
    private $templateModel;
    private $shopModel;

    use ShopContainerServicePart1,
        ShopContainerServicePart2;
}
