<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;

/**
 * Inicializa Eloquent ORM con los datos del archivo .env.
 * Se asume que phpdotenv ya fue cargado en public/index.php.
 *
 * - "default": base de datos propia del microservicio.
 * - "auth":    conexión de SOLO LECTURA a la BD de ms-auth, usada únicamente
 *              por AuthMiddleware para validar el token y la sesión activa.
 * - "empleados": conexión de SOLO LECTURA a la BD de ms-empleados, para comprobar
 *              que el empleado exista y mostrar su nombre en los listados.
 */
$capsule = new Capsule();

$comunes = [
    'driver'    => $_ENV['DB_DRIVER'] ?? 'mysql',
    'charset'   => $_ENV['DB_CHARSET'] ?? 'utf8mb4',
    'collation' => $_ENV['DB_COLLATION'] ?? 'utf8mb4_unicode_ci',
    'prefix'    => '',
];

$capsule->addConnection($comunes + [
    'host'     => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'port'     => $_ENV['DB_PORT'] ?? '3306',
    'database' => $_ENV['DB_DATABASE'] ?? '',
    'username' => $_ENV['DB_USERNAME'] ?? 'root',
    'password' => $_ENV['DB_PASSWORD'] ?? '',
]);

$capsule->addConnection($comunes + [
    'host'     => $_ENV['AUTH_DB_HOST'] ?? '127.0.0.1',
    'port'     => $_ENV['AUTH_DB_PORT'] ?? '3306',
    'database' => $_ENV['AUTH_DB_DATABASE'] ?? 'ms-auth',
    'username' => $_ENV['AUTH_DB_USERNAME'] ?? 'root',
    'password' => $_ENV['AUTH_DB_PASSWORD'] ?? '',
], 'auth');

$capsule->addConnection($comunes + [
    'host'     => $_ENV['EMPLEADOS_DB_HOST'] ?? '127.0.0.1',
    'port'     => $_ENV['EMPLEADOS_DB_PORT'] ?? '3306',
    'database' => $_ENV['EMPLEADOS_DB_DATABASE'] ?? 'ms-empleados',
    'username' => $_ENV['EMPLEADOS_DB_USERNAME'] ?? 'root',
    'password' => $_ENV['EMPLEADOS_DB_PASSWORD'] ?? '',
], 'empleados');

$capsule->setAsGlobal();
$capsule->bootEloquent();

return $capsule;
