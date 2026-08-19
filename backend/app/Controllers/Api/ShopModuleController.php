<?php

namespace App\Controllers\Api;

use App\Controllers\BaseController;
use CodeIgniter\API\ResponseTrait;
use App\Models\ShopModel;
use App\Models\ShopTypeModel;
use App\Models\ShopTemplateModel;
use App\Models\ShopContainerModel;
use App\Models\ShopContainerTemplateItemModel;
use App\Models\ShopContainerInstanceItemModel;
use App\Models\ShopItemInstanceModel;
use App\Services\CharacterAssetService;
use App\Services\Shop\AuthContextService;
use App\Services\Shop\ShopAuthorizationService;
use App\Services\Shop\ShopBootstrapService;
use App\Services\Shop\ShopCatalogService;
use App\Services\Shop\ShopContainerService;
use App\Services\Shop\ShopCurrencyService;
use App\Services\Shop\ShopLegacyMapper;
use App\Services\Shop\ShopItemIconResolver;
use App\Services\Shop\ShopIconMetadataService;
use App\Services\Shop\ShopPricingService;
use App\Services\Shop\ShopProfileService;
use App\Services\Shop\ShopSuggestionService;
use App\Services\Shop\ShopTradeLedgerService;
use App\Services\Shop\ShopTradeService;

class ShopModuleController extends BaseController
{
    use ResponseTrait;

    private $authContextService;
    private $authorizationService;
    private $bootstrapService;
    private $catalogService;
    private $containerService;
    private $currencyService;
    private $profileService;
    private $suggestionService;
    private $tradeLedgerService;
    private $tradeService;
    private $mapper;
    private $shopModel;
    private $shopTypeModel;
    private $templateModel;
    private $containerModel;
    private $containerTemplateItemModel;
    private $containerInstanceItemModel;
    private $itemInstanceModel;
    private $itemIconResolver;
    private $iconMetadataService;
    private $characterAssetService;

    use ShopModuleControllerPart1,
        ShopModuleControllerPart2,
        ShopModuleControllerPart3,
        ShopModuleControllerPart4,
        ShopModuleControllerPart5,
        ShopModuleControllerPart6,
        ShopModuleControllerPart7;
}
