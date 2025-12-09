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

    // 1. PUBLICZNE (Bez filtra)
    $routes->post('register', 'AuthController::register'); // Każdy może się zarejestrować
    $routes->post('login', 'AuthController::login');       // Każdy może spróbować się zalogować

    // 2. PRYWATNE (Z filtrem 'auth')
    $routes->group('', ['filter' => 'auth', 'namespace' => 'App\Controllers\Api'], function($routes) {

        // endpoint testowy usera
        $routes->get('me', function() {
            return response()->setJSON(['message' => 'Token działa!']);
        });

        // Do tych tras wejdzie tylko ktoś z ważnym tokenem JWT
        $routes->resource('characters',['controller' => 'CharacterController']); // CRUD Postaci
  
        // --- SEKCJA GIER (NOWOŚĆ) ---
        // Lista wszystkich skonfigurowanych par System+Setting
        $routes->get('games', 'RpgCatalogController::listGames');

        // --- SEKCJA UNIWERSÓW ---
        // Pozwala pobrać konkretne uniwersum po ID: GET /api/universes/7
        $routes->get('universes/(:num)', 'RpgCatalogController::showUniverse/$1');

        // --- SEKCJA SYSTEMÓW ---
        // UNIWERSA (Niestandardowa akcja w kontrolerze systemów)
        $routes->get('systems/(:num)/universes', 'RpgCatalogController::systemUniverses/$1');      
        // Kategorie dla systemu
        $routes->get('systems/(:num)/categories', 'GameDataController::getCategories/$1');
        
        // Lista definicji dla systemu
        $routes->get('systems/(:num)/data', 'GameDataController::getDefinitions/$1');
        
        // KATALOG SYSTEMÓW (Resource)
        $routes->resource('systems', [
            'controller' => 'RpgCatalogController',
            'only'       => ['index', 'show'] // Ograniczamy tylko do odczytu
        ]);
        
        // Pojedyncze traity (NOWE)
        $routes->get('traits/(:num)', 'GameDataController::show/$1');
    });
     
});