<?php

declare(strict_types=1);

namespace App\Middleware;

use Illuminate\Database\QueryException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Interfaces\ErrorRendererInterface;
use Throwable;

/**
 * Convierte los errores de la aplicación al formato común
 * { "ok": false, "mensaje": string, "data": null|{detalle} }.
 */
class JsonErrorRenderer implements ErrorRendererInterface
{
    public function __invoke(Throwable $exception, bool $displayErrorDetails): string
    {
        $mensaje = match (true) {
            $exception instanceof HttpNotFoundException         => 'La ruta solicitada no existe.',
            $exception instanceof HttpMethodNotAllowedException => 'Método HTTP no permitido para esta ruta.',
            $exception instanceof HttpException                 => $exception->getMessage(),
            $exception instanceof QueryException                => 'Error al consultar la base de datos.',
            default                                             => 'Error interno del servidor.',
        };

        $data = $displayErrorDetails && !$exception instanceof HttpException
            ? ['detalle' => $exception->getMessage()]
            : null;

        return (string) json_encode([
            'ok'      => false,
            'mensaje' => $mensaje,
            'data'    => $data,
        ], JSON_UNESCAPED_UNICODE);
    }
}
