<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * Controlador base: todas las respuestas del servicio usan el formato
 * { "ok": bool, "mensaje": string, "data": mixed }.
 */
abstract class Controller
{
    protected function json(Response $response, bool $ok, string $mensaje, mixed $data = null, int $status = 200): Response
    {
        $payload = [
            'ok'      => $ok,
            'mensaje' => $mensaje,
            'data'    => $data,
        ];

        $response->getBody()->write(json_encode($payload, JSON_UNESCAPED_UNICODE));

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}
