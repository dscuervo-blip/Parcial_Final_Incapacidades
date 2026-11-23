<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\EmpleadoConsulta;
use App\Models\Incapacidad;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class IncapacidadController extends Controller
{
    /**
     * GET /incapacidades
     * Filtros opcionales: ?empleado_id=&empleado=&estado=&tipo=&fecha_desde=&fecha_hasta=
     */
    public function index(Request $request, Response $response): Response
    {
        $parametros = $request->getQueryParams();
        $leer = fn (string $clave): string => trim((string) ($parametros[$clave] ?? ''));

        $filtros = [
            'estado'      => $leer('estado'),
            'tipo'        => $leer('tipo'),
            'fecha_desde' => $leer('fecha_desde'),
            'fecha_hasta' => $leer('fecha_hasta'),
        ];

        $errores = [];

        if ($filtros['estado'] !== '' && !array_key_exists($filtros['estado'], Incapacidad::ESTADOS)) {
            $errores['estado'] = 'El estado no es válido.';
        }

        if ($filtros['tipo'] !== '' && !array_key_exists($filtros['tipo'], Incapacidad::TIPOS)) {
            $errores['tipo'] = 'El tipo de incapacidad no es válido.';
        }

        foreach (['fecha_desde' => 'La fecha inicial', 'fecha_hasta' => 'La fecha final'] as $campo => $nombre) {
            if ($filtros[$campo] !== '' && !$this->fechaValida($filtros[$campo])) {
                $errores[$campo] = "{$nombre} no es válida (formato AAAA-MM-DD).";
            }
        }

        if (!isset($errores['fecha_desde']) && !isset($errores['fecha_hasta'])
            && $filtros['fecha_desde'] !== '' && $filtros['fecha_hasta'] !== ''
            && $filtros['fecha_hasta'] < $filtros['fecha_desde']) {
            $errores['fecha_hasta'] = 'La fecha final no puede ser anterior a la inicial.';
        }

        $empleadoId = $leer('empleado_id');
        if ($empleadoId !== '' && !ctype_digit($empleadoId)) {
            $errores['empleado_id'] = 'El id del empleado debe ser numérico.';
        }

        if ($errores !== []) {
            return $this->json($response, false, 'Revise los filtros de búsqueda.', ['errores' => $errores], 422);
        }

        // Búsqueda por empleado: por id exacto o por nombre / documento.
        $textoEmpleado = $leer('empleado');
        if ($empleadoId !== '') {
            $filtros['empleado_ids'] = [(int) $empleadoId];
        } elseif ($textoEmpleado !== '') {
            $filtros['empleado_ids'] = EmpleadoConsulta::idsQueCoinciden($textoEmpleado);
        }

        $incapacidades = Incapacidad::filtrar($filtros)
            ->orderByDesc('fecha_inicio')
            ->orderByDesc('id')
            ->get();

        $mensaje = $incapacidades->isEmpty()
            ? 'No se encontraron incapacidades con los filtros indicados.'
            : 'Incapacidades consultadas correctamente.';

        return $this->json($response, true, $mensaje, [
            'total'         => $incapacidades->count(),
            'incapacidades' => $this->conEmpleado($incapacidades),
        ]);
    }

    /**
     * GET /incapacidades/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $incapacidad = Incapacidad::find((int) $args['id']);

        if ($incapacidad === null) {
            return $this->json($response, false, 'La incapacidad no existe.', null, 404);
        }

        return $this->json($response, true, 'Incapacidad consultada correctamente.', $this->conEmpleado(collect([$incapacidad]))[0]);
    }

    /**
     * GET /incapacidades/catalogos
     * Tipos y estados permitidos, para llenar filtros y formularios del frontend.
     */
    public function catalogos(Request $request, Response $response): Response
    {
        $aLista = fn (array $valores): array => array_map(
            fn (string $valor, string $etiqueta): array => ['valor' => $valor, 'etiqueta' => $etiqueta],
            array_keys($valores),
            $valores
        );

        return $this->json($response, true, 'Catálogos consultados correctamente.', [
            'tipos'   => $aLista(Incapacidad::TIPOS),
            'estados' => $aLista(Incapacidad::ESTADOS),
        ]);
    }

    /**
     * Agrega a cada incapacidad los datos básicos de su empleado (consulta de solo lectura).
     */
    private function conEmpleado(Collection $incapacidades): array
    {
        $empleados = EmpleadoConsulta::resumenPorIds($incapacidades->pluck('empleado_id')->all());

        return $incapacidades->map(function (Incapacidad $incapacidad) use ($empleados): array {
            $empleado = $empleados->get($incapacidad->empleado_id);

            return $incapacidad->toArray() + [
                'empleado' => $empleado?->resumen(),
            ];
        })->all();
    }

    private function fechaValida(string $valor): bool
    {
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

        return $fecha !== false && $fecha->format('Y-m-d') === $valor;
    }
}
