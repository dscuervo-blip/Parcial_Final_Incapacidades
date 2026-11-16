<?php

declare(strict_types=1);

namespace App\Controllers;

use Illuminate\Database\Capsule\Manager as Capsule;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Ruta de prueba: confirma que el servicio está arriba y si conecta a su base de datos.
 */
class HomeController
{
    public function index(Request $request, Response $response): Response
    {
        try {
            Capsule::connection()->getPdo();
            $baseDatos = [
                'conectada' => true,
                'nombre'    => Capsule::connection()->getDatabaseName(),
            ];
        } catch (\Throwable $e) {
            $baseDatos = [
                'conectada' => false,
                'error'     => $e->getMessage(),
            ];
        }

        $payload = [
            'servicio'   => $_ENV['APP_NAME'] ?? 'ms-incapacidades',
            'estado'     => 'funcionando',
            'base_datos' => $baseDatos,
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
