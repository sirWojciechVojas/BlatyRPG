<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Pobieramy trasy systemowe - nie usuwać!
$routes->get('/', 'Home::index');

// 🔧 Konfiguracja API
// Wyłączamy automatyczny routing, żeby mieć pełną kontrolę
$routes->setAutoRoute(true);

// Grupa API z jawnym wskazaniem namespace'a
$routes->group('', ['namespace' => 'App\Controllers\Api'], function ($routes) {
  
     $routes->resource('heroes');
     $routes->resource('gameDataControler');
     $routes->resource('rpgCatalogController');

});