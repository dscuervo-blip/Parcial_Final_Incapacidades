<?php

declare(strict_types=1);

use App\Controllers\HomeController;
use Slim\App;

return function (App $app): void {
    // Ruta de prueba
    $app->get('/', [HomeController::class, 'index']);

    // Aquí se registrarán los endpoints del microservicio.
};
