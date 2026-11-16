<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Usuario;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController extends Controller
{
    /**
     * POST /login
     * Body: { "usuario": "usuario o correo", "contrasena": "..." }
     */
    public function login(Request $request, Response $response): Response
    {
        $body = (array) ($request->getParsedBody() ?? []);
        $identificador = trim((string) ($body['usuario'] ?? ''));
        $contrasena = (string) ($body['contrasena'] ?? '');

        if ($identificador === '' || $contrasena === '') {
            return $this->json($response, false, 'El usuario o correo y la contraseña son obligatorios.', null, 422);
        }

        $usuario = Usuario::where('usuario', $identificador)
            ->orWhere('correo', $identificador)
            ->first();

        if (!$usuario || !$this->contrasenaValida($usuario, $contrasena)) {
            return $this->json($response, false, 'Usuario o contraseña incorrectos.', null, 401);
        }

        if ($usuario->estado !== 'activo') {
            return $this->json($response, false, 'El usuario está inactivo. Contacte al administrador.', null, 403);
        }

        $usuario->token = bin2hex(random_bytes(32));
        $usuario->sesion_activa = 1;
        $usuario->save();

        return $this->json($response, true, 'Inicio de sesión exitoso.', [
            'token'   => $usuario->token,
            'usuario' => [
                'id'      => $usuario->id,
                'nombre'  => $usuario->nombre,
                'correo'  => $usuario->correo,
                'usuario' => $usuario->usuario,
                'rol'     => $usuario->rol,
            ],
        ]);
    }

    /**
     * POST /logout
     * Header: Authorization: Bearer <token>
     */
    public function logout(Request $request, Response $response): Response
    {
        $token = $this->tokenDesdeRequest($request);

        if ($token === null) {
            return $this->json($response, false, 'No se envió el token de sesión.', null, 401);
        }

        $usuario = Usuario::where('token', $token)->first();

        if (!$usuario) {
            return $this->json($response, false, 'La sesión no existe o ya fue cerrada.', null, 401);
        }

        $usuario->token = null;
        $usuario->sesion_activa = 0;
        $usuario->save();

        return $this->json($response, true, 'Sesión cerrada correctamente.');
    }

    /**
     * Acepta contraseñas cifradas con password_hash. Si la contraseña guardada
     * está en texto plano (datos de prueba), la compara y la cifra en ese momento.
     */
    private function contrasenaValida(Usuario $usuario, string $contrasena): bool
    {
        $guardada = (string) $usuario->contrasena;

        if (password_get_info($guardada)['algo'] !== null) {
            return password_verify($contrasena, $guardada);
        }

        if (!hash_equals($guardada, $contrasena)) {
            return false;
        }

        $usuario->contrasena = password_hash($contrasena, PASSWORD_DEFAULT);

        return true;
    }

    private function tokenDesdeRequest(Request $request): ?string
    {
        $cabecera = $request->getHeaderLine('Authorization');

        if (preg_match('/^Bearer\s+(\S+)$/i', $cabecera, $coincidencia)) {
            return $coincidencia[1];
        }

        return null;
    }
}
