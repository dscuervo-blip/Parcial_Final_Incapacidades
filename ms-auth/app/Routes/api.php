<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\HomeController;
use App\Controllers\SesionController;
use App\Middleware\AuthMiddleware;
use Slim\App;

return function (App $app): void {
    // Ruta de prueba
    $app->get('/', [HomeController::class, 'index']);

    // Autenticación (públicas)
    $app->post('/login', [AuthController::class, 'login']);
    $app->post('/logout', [AuthController::class, 'logout']);

    // Rutas protegidas
    $app->get('/sesion', [SesionController::class, 'actual'])->add(new AuthMiddleware());
};
