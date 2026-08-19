<?php

namespace App\Services\Shop;

use App\Models\ShopCatalogNodeModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopItemInstanceModel;
use App\Models\ShopProfileModel;
use App\Models\ShopSuggestionCacheModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopTypeModel;

class ShopSuggestionService
{
    private $db;
    private $cacheModel;
    private $templateModel;
    private $profileModel;
    private $catalogNodeModel;
    private $shopTypeModel;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $itemInstanceModel;
    private $containerService;
    private $itemIconResolver;
    private $currencyService;

    use ShopSuggestionServicePart1,
        ShopSuggestionServicePart2,
        ShopSuggestionServicePart3,
        ShopSuggestionServicePart4,
        ShopSuggestionServicePart5,
        ShopSuggestionServicePart6;
}
