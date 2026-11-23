<?php

declare(strict_types=1);

use App\Controllers\EmpleadoController;
use App\Controllers\HomeController;
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

    // Gestión de empleados: solo administrador y gestión humana
    $app->group('/empleados', function (RouteCollectorProxy $grupo): void {
        $grupo->get('', [EmpleadoController::class, 'index']);
        $grupo->get('/areas', [EmpleadoController::class, 'areas']);
        $grupo->get('/{id:[0-9]+}', [EmpleadoController::class, 'show']);
        $grupo->post('', [EmpleadoController::class, 'store']);
        $grupo->put('/{id:[0-9]+}', [EmpleadoController::class, 'update']);
        $grupo->patch('/{id:[0-9]+}/estado', [EmpleadoController::class, 'cambiarEstado']);
    })->add(new AuthMiddleware(['administrador', 'gestion_humana']));
};
