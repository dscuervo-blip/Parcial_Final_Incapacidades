<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Inicializa Eloquent ORM con los datos de conexión del archivo .env.
 * Se asume que phpdotenv ya fue cargado en public/index.php.
 */
$capsule = new Capsule();

$capsule->addConnection([
    'driver'    => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host'      => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'      => $_ENV['DB_PORT'] ?? '3306',
    'database'  => $_ENV['DB_DATABASE'] ?? '',
    'username'  => $_ENV['DB_USERNAME'] ?? 'root',
    'password'  => $_ENV['DB_PASSWORD'] ?? '',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    'prefix'    => '',
]);

$capsule->setAsGlobal();
$capsule->bootEloquent();

return $capsule;
