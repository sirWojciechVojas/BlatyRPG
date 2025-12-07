<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */

// Pobieramy trasy systemowe - nie usuwać!
$routes->get('/', 'Home::index');

// 🔧 Konfiguracja API
// Wyłączamy automatyczny routing, żeby mieć pełną kontrolę
$routes->setAutoRoute(false);

// Grupa API z jawnym wskazaniem namespace'a
$routes->group('', ['namespace' => 'App\Controllers\Api'], function ($routes) {

    // 1. PUBLICZNE (Bez filtra)
    $routes->post('register', 'AuthController::register'); // Każdy może się zarejestrować
    $routes->post('login', 'AuthController::login');       // Każdy może spróbować się zalogować

    // 2. PRYWATNE (Z filtrem 'auth')
    $routes->group('', ['filter' => 'auth'], function($routes) {

        // endpoint testowy usera
        $routes->get('me', function() {
            return response()->setJSON(['message' => 'Token działa!']);
        });

        // Do tych tras wejdzie tylko ktoś z ważnym tokenem JWT
        $routes->resource('characters'); // CRUD Postaci
        $routes->resource('campaigns');  // CRUD Kampanii
    });
     
});