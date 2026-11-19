<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Empleado;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmpleadoController extends Controller
{
    /**
     * GET /empleados
     * Filtros opcionales: ?documento=&area=&estado=&buscar=
     */
    public function index(Request $request, Response $response): Response
    {
        $parametros = $request->getQueryParams();

        $filtros = [
            'documento' => trim((string) ($parametros['documento'] ?? '')),
            'area'      => trim((string) ($parametros['area'] ?? '')),
            'estado'    => trim((string) ($parametros['estado'] ?? '')),
            'buscar'    => trim((string) ($parametros['buscar'] ?? '')),
        ];

        if ($filtros['estado'] !== '' && !in_array($filtros['estado'], Empleado::ESTADOS, true)) {
            return $this->json($response, false, 'El estado debe ser "activo" o "inactivo".', null, 422);
        }

        $empleados = Empleado::filtrar($filtros)
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        $mensaje = $empleados->isEmpty()
            ? 'No se encontraron empleados con los filtros indicados.'
            : 'Empleados consultados correctamente.';

        return $this->json($response, true, $mensaje, [
            'total'     => $empleados->count(),
            'empleados' => $empleados,
        ]);
    }

    /**
     * GET /empleados/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $empleado = Empleado::find((int) $args['id']);

        if ($empleado === null) {
            return $this->json($response, false, 'El empleado no existe.', null, 404);
        }

        return $this->json($response, true, 'Empleado consultado correctamente.', $empleado);
    }

    /**
     * GET /empleados/areas
     * Lista de áreas registradas, para los filtros del frontend.
     */
    public function areas(Request $request, Response $response): Response
    {
        $areas = Empleado::query()
            ->select('area')
            ->distinct()
            ->orderBy('area')
            ->pluck('area');

        return $this->json($response, true, 'Áreas consultadas correctamente.', $areas);
    }
}
