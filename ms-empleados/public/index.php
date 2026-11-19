<?php

declare(strict_types=1);

use App\Middleware\CorsMiddleware;
use App\Middleware\JsonErrorRenderer;
use Dotenv\Dotenv;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// 1. Variables de entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();
$dotenv->required(['DB_HOST', 'DB_DATABASE', 'DB_USERNAME']);

// Zona horaria de la aplicación (debe coincidir con la de MySQL).
date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/Bogota');

// 2. Base de datos (Eloquent)
require __DIR__ . '/../app/Config/database.php';

// 3. Aplicación Slim
$app = AppFactory::create();

$app->addBodyParsingMiddleware();
$app->addRoutingMiddleware();

$debug = filter_var($_ENV['APP_DEBUG'] ?? false, FILTER_VALIDATE_BOOLEAN);
$errorMiddleware = $app->addErrorMiddleware($debug, true, true);
// Los errores (404, 405, 500...) siempre se devuelven en JSON.
$errorHandler = $errorMiddleware->getDefaultErrorHandler();
$errorHandler->registerErrorRenderer('application/json', JsonErrorRenderer::class);
$errorHandler->forceContentType('application/json');

// CORS se agrega al final para que envuelva también las respuestas de error.
$app->add(new CorsMiddleware());

// 4. Rutas
(require __DIR__ . '/../app/Routes/api.php')($app);

$app->run();
