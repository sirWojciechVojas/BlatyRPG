<?php

namespace App\Services\Shop;

class ShopPricingService
{
    private $catalogService;
    private $typeLookup;
    private $worldLookup;

    use ShopPricingServicePart1,
        ShopPricingServicePart2,
        ShopPricingServicePart3,
        ShopPricingServicePart4,
        ShopPricingServicePart5,
        ShopPricingServicePart6,
        ShopPricingServicePart7,
        ShopPricingServicePart8,
        ShopPricingServicePart9;
}
