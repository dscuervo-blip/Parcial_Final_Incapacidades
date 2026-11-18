<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SesionController extends Controller
{
    /**
     * GET /sesion (protegida)
     * Confirma que este microservicio acepta el token y devuelve el usuario autenticado.
     */
    public function actual(Request $request, Response $response): Response
    {
        return $this->json($response, true, 'Sesión válida.', [
            'servicio' => $_ENV['APP_NAME'] ?? null,
            'usuario'  => $request->getAttribute('usuario'),
        ]);
    }
}
