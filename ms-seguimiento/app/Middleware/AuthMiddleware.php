<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Models\SesionUsuario;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\Psr7\Response as SlimResponse;

/**
 * Protege las rutas: exige el header "Authorization: Bearer <token>",
 * que el token exista y que la sesión del usuario esté activa.
 *
 * Opcionalmente restringe por rol: new AuthMiddleware(['administrador']).
 * Si la validación pasa, deja el usuario en el atributo "usuario" del request.
 */
class AuthMiddleware implements MiddlewareInterface
{
    /** @param string[] $roles Roles permitidos; vacío = cualquier usuario autenticado. */
    public function __construct(private array $roles = [])
    {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $token = $this->extraerToken($request);

        if ($token === null) {
            return $this->error('No autenticado: falta el token de sesión.', 401);
        }

        $usuario = SesionUsuario::porTokenActivo($token);

        if ($usuario === null) {
            return $this->error('Sesión inválida o expirada. Inicie sesión nuevamente.', 401);
        }

        if ($this->roles !== [] && !in_array($usuario->rol, $this->roles, true)) {
            return $this->error('No tiene permisos para realizar esta acción.', 403);
        }

        $request = $request->withAttribute('usuario', [
            'id'      => $usuario->id,
            'nombre'  => $usuario->nombre,
            'correo'  => $usuario->correo,
            'usuario' => $usuario->usuario,
            'rol'     => $usuario->rol,
        ]);

        return $handler->handle($request);
    }

    private function extraerToken(Request $request): ?string
    {
        $cabecera = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(\S+)$/i', $cabecera, $coincidencia)) {
            return $coincidencia[1];
        }

        return null;
    }

    private function error(string $mensaje, int $status): Response
    {
        $response = new SlimResponse($status);
        $response->getBody()->write(json_encode([
            'ok'      => false,
            'mensaje' => $mensaje,
            'data'    => null,
        ], JSON_UNESCAPED_UNICODE));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8');
    }
}
