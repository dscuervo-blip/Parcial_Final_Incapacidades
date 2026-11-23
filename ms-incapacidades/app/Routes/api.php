<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use App\Controllers\IncapacidadController;
use App\Controllers\SesionController;
use App\Middleware\AuthMiddleware;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

return function (App $app): void {
    // Ruta de prueba (pública)
    $app->get('/', [HomeController::class, 'index']);

    // Rutas protegidas: requieren token y sesión activa
    $app->group('', function (RouteCollectorProxy $grupo): void {
        $grupo->get('/sesion', [SesionController::class, 'actual']);
    })->add(new AuthMiddleware());

    // Gestión de incapacidades: solo administrador y gestión humana
    $app->group('/incapacidades', function (RouteCollectorProxy $grupo): void {
        $grupo->get('', [IncapacidadController::class, 'index']);
        $grupo->get('/catalogos', [IncapacidadController::class, 'catalogos']);
        $grupo->get('/{id:[0-9]+}', [IncapacidadController::class, 'show']);
    })->add(new AuthMiddleware(['administrador', 'gestion_humana']));
};
