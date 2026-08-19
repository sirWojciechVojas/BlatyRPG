<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// ========================================
// BASE ROUTER CONFIG
// ========================================
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override();
$routes->setAutoRoute(false);

// ========================================
// ROOT
// API-only app entrypoint
// ========================================
$routes->get('/', 'Api\StatusController::app');

// ========================================
// API
// Everything under /api
// ========================================
$routes->group('api', ['namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
    // ----------------------------------------
    // STATUS / HEALTH
    // ----------------------------------------
    $routes->get('/', 'StatusController::index');
    $routes->get('health', 'StatusController::health');

    // ----------------------------------------
    // AUTH (PUBLIC)
    // Canonical: /api/auth/*
    // Legacy aliases: /api/login, /api/register
    // ----------------------------------------
    $routes->group('auth', static function (RouteCollection $routes) {
        $routes->post('login', 'AuthController::login');
        $routes->post('register', 'AuthController::register');
    });

    // $routes->post('login', 'AuthController::login');
    // $routes->post('register', 'AuthController::register');

    // ----------------------------------------
    // PRIVATE ENDPOINTS
    // ----------------------------------------
    $routes->group('', ['filter' => 'auth', 'namespace' => 'App\Controllers\Api'], static function (RouteCollection $routes) {
        // AUTH (PRIVATE)
        // Canonical: /api/auth/me
        // Legacy alias: /api/me
        $routes->get('auth/me', 'AuthSessionController::me');
        $routes->get('me', 'AuthSessionController::me');

        // ----------------------------------------
        // CAMPAIGNS
        // ----------------------------------------
        $routes->get('campaigns', 'CampaignController::index');
        $routes->post('campaigns', 'CampaignController::create');
        $routes->get('campaigns/(:num)/chat/messages', 'CampaignChatController::index/$1');
        $routes->post('campaigns/(:num)/chat/messages', 'CampaignChatController::create/$1');

        // ----------------------------------------
        // ADMINISTRATION
        // ----------------------------------------
        $routes->get('admin/overview', 'AdminController::overview');
        $routes->post('admin/users', 'AdminController::createUser');
        $routes->patch('admin/users/(:num)/role', 'AdminController::changeUserRole/$1');

        // ----------------------------------------
        // CHARACTERS
        // ----------------------------------------
        $routes->get('character-asset-sets/available', 'CharacterController::availableAssetSets');
        $routes->post('character-asset-sets', 'CharacterController::createAssetSet');
        $routes->get('characters/(:num)/assets', 'CharacterController::assets/$1');
        $routes->put('characters/(:num)/asset-set', 'CharacterController::assignAssetSet/$1');
        $routes->resource('characters', [
            'controller' => 'CharacterController',
        ]);

        // ----------------------------------------
        // GAMES
        // ----------------------------------------
        $routes->get('games', 'RpgCatalogController::listGames');

        // ----------------------------------------
        // UNIVERSES
        // ----------------------------------------
        $routes->get('universes/(:num)', 'RpgCatalogController::showUniverse/$1');

        // ----------------------------------------
        // SYSTEMS
        // ----------------------------------------
        $routes->get('systems/(:num)/universes', 'RpgCatalogController::systemUniverses/$1');
        $routes->get('systems/(:num)/categories', 'GameDataController::getCategories/$1');
        $routes->get('systems/(:num)/data', 'GameDataController::getDefinitions/$1');
        $routes->resource('systems', [
            'controller' => 'RpgCatalogController',
            'only'       => ['index', 'show'],
        ]);

        // ----------------------------------------
        // TRAITS
        // ----------------------------------------
        $routes->get('traits/(:num)', 'GameDataController::show/$1');

        // ----------------------------------------
        // VTT SCENES
        // /api/campaigns/{campaignId}/scenes
        // ----------------------------------------
        $routes->get('campaigns/(:num)/scenes', 'SceneController::index/$1');
        $routes->post('campaigns/(:num)/scenes', 'SceneController::create/$1');
        $routes->get('campaigns/(:num)/scenes/(:num)', 'SceneController::show/$1/$2');
        $routes->patch('campaigns/(:num)/scenes/(:num)', 'SceneController::update/$1/$2');
        $routes->delete('campaigns/(:num)/scenes/(:num)', 'SceneController::delete/$1/$2');
        $routes->post('campaigns/(:num)/scenes/(:num)/activate', 'SceneController::activate/$1/$2');

        // ----------------------------------------
        // SHOP MODULE
        // /api/shop/campaigns/{campaignId}/...
        // ----------------------------------------
        $routes->group('shop/campaigns/(:num)', [
            'filter' => 'shopCampaignAccess',
            'namespace' => 'App\\Controllers\\Api',
        ], static function (RouteCollection $routes) {
            // Bootstrap + reference data
            $routes->get('access/options', 'ShopModuleController::accessOptions/$1');
            $routes->get('bootstrap', 'ShopModuleController::bootstrap/$1');
            $routes->get('catalog/network', 'ShopModuleController::getCatalog/$1');
            $routes->get('catalog/item-dictionaries', 'ShopModuleController::getItemDictionaries/$1');
            $routes->post('catalog/item-dictionaries', 'ShopModuleController::createItemDictionaryEntry/$1');
            $routes->put('catalog/item-dictionaries/(:num)', 'ShopModuleController::updateItemDictionaryEntry/$1/$2');
            $routes->delete('catalog/item-dictionaries/(:num)', 'ShopModuleController::deleteItemDictionaryEntry/$1/$2');
            $routes->get('catalog/currencies', 'ShopModuleController::getCurrencies/$1');
            $routes->get('catalog/icon-metadata', 'ShopModuleController::getIconMetadata/$1');
            $routes->post('catalog/icon-metadata', 'ShopModuleController::uploadIcon/$1');
            $routes->post('catalog/icon-metadata/(:segment)/images', 'ShopModuleController::replaceIconImages/$1/$2');
            $routes->put('catalog/icon-metadata/(:segment)', 'ShopModuleController::putIconMetadata/$1/$2');
            $routes->delete('catalog/icon-metadata/(:segment)', 'ShopModuleController::deleteIconMetadata/$1/$2');
            $routes->get('world-profiles', 'ShopModuleController::getWorldProfiles/$1');

            // Shops
            $routes->get('shops', 'ShopModuleController::listShops/$1');
            $routes->post('shops', 'ShopModuleController::createShop/$1');
            $routes->get('shops/(:num)', 'ShopModuleController::showShop/$1/$2');
            $routes->patch('shops/(:num)', 'ShopModuleController::updateShop/$1/$2');
            $routes->delete('shops/(:num)', 'ShopModuleController::deleteShop/$1/$2');
            $routes->post('shops/(:num)/duplicate', 'ShopModuleController::duplicateShop/$1/$2');
            $routes->patch('shops/(:num)/activation', 'ShopModuleController::updateShopActivation/$1/$2');

            // Shop profile
            $routes->get('shops/(:num)/profile', 'ShopModuleController::getShopProfile/$1/$2');
            $routes->put('shops/(:num)/profile', 'ShopModuleController::putShopProfile/$1/$2');
            $routes->get('shops/(:num)/profile/history', 'ShopModuleController::getShopProfileHistory/$1/$2');
            $routes->get('shops/(:num)/profile/export', 'ShopModuleController::exportShopProfile/$1/$2');
            $routes->post('shops/(:num)/profile/import', 'ShopModuleController::importShopProfile/$1/$2');
            $routes->post('shops/(:num)/pricing/preview', 'ShopModuleController::previewShopPricing/$1/$2');

            // Templates
            $routes->get('templates', 'ShopModuleController::listTemplates/$1');
            $routes->post('templates', 'ShopModuleController::createTemplate/$1');
            $routes->put('templates/(:num)', 'ShopModuleController::updateTemplate/$1/$2');
            $routes->delete('templates/(:num)', 'ShopModuleController::deleteTemplate/$1/$2');
            $routes->post('templates/(:num)/restore', 'ShopModuleController::restoreTemplate/$1/$2');
            $routes->post('templates/(:num)/duplicate', 'ShopModuleController::duplicateTemplate/$1/$2');

            // Personalized item instances
            $routes->patch('item-instances/(:num)', 'ShopModuleController::updateItemInstance/$1/$2');
            $routes->post('item-instances', 'ShopModuleController::createItemInstance/$1');

            // Containers
            $routes->get('containers', 'ShopModuleController::getContainers/$1');
            $routes->post('containers/move', 'ShopModuleController::moveContainer/$1');
            $routes->patch('containers/quantities', 'ShopModuleController::setContainerQuantities/$1');
            $routes->post('containers/buy', 'ShopModuleController::buyFromContainer/$1');
            $routes->post('containers/trash', 'ShopModuleController::trashContainerItem/$1');
            $routes->post('containers/restore', 'ShopModuleController::restoreContainerItem/$1');
            $routes->post('containers/merge', 'ShopModuleController::mergeContainerItems/$1');

            // Player trade
            $routes->post('trade/buy/quote', 'ShopModuleController::quoteTradeBuyPayment/$1');
            $routes->post('trade/buy', 'ShopModuleController::tradeBuy/$1');
            $routes->post('trade/sell', 'ShopModuleController::tradeSell/$1');
            $routes->get('trade/ledger', 'ShopModuleController::listTradeLedger/$1');
            $routes->post('trade/ledger/(:num)/reverse', 'ShopModuleController::reverseTradeLedger/$1/$2');
            $routes->post('trade/ledger/(:num)/redo', 'ShopModuleController::redoTradeLedger/$1/$2');
            $routes->post('trade/ledger/(:num)/correct', 'ShopModuleController::correctTradeLedger/$1/$2');

            // Assortment
            $routes->post('shops/(:num)/assortment/replace', 'ShopModuleController::replaceAssortment/$1/$2');
            $routes->post('shops/(:num)/assortment/transfer', 'ShopModuleController::transferAssortment/$1/$2');
            $routes->post('shops/(:num)/assortment/roll', 'ShopModuleController::rollAssortment/$1/$2');

            // Suggestions
            $routes->post('shops/(:num)/suggestions/generate', 'ShopModuleController::generateSuggestions/$1/$2');
            $routes->get('shops/(:num)/suggestions', 'ShopModuleController::getSuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/promote', 'ShopModuleController::promoteSuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/apply', 'ShopModuleController::applySuggestions/$1/$2');
            $routes->post('shops/(:num)/suggestions/materialize', 'ShopModuleController::materializeSuggestion/$1/$2');
        });
    });
});
