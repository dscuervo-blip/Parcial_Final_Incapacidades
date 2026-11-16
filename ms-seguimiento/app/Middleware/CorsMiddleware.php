<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Agrega las cabeceras CORS para que el frontend pueda consumir el servicio.
 */
class CorsMiddleware implements MiddlewareInterface
{
    public function process(Request $request, RequestHandler $handler): Response
    {
        // Las peticiones preflight (OPTIONS) se responden de inmediato.
        $response = $request->getMethod() === 'OPTIONS'
            ? new SlimResponse(204)
            : $handler->handle($request);

        $origin = $_ENV['CORS_ALLOWED_ORIGIN'] ?? '*';

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
    }
}
