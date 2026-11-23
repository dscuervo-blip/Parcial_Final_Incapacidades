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
    /** Campos obligatorios y el mensaje cuando faltan. */
    private const CAMPOS = [
        'empleado_id'         => 'Seleccione el empleado.',
        'fecha_inicio'        => 'La fecha de inicio es obligatoria.',
        'fecha_fin'           => 'La fecha de fin es obligatoria.',
        'tipo'                => 'Seleccione el tipo de incapacidad.',
        'diagnostico_general' => 'El diagnóstico es obligatorio.',
        'entidad_medica'      => 'La entidad médica es obligatoria.',
    ];

    /** Longitud máxima de los campos de texto. */
    private const LONGITUDES = [
        'diagnostico_general' => 1000,
        'entidad_medica'      => 150,
        'observaciones'       => 1000,
    ];

    /** Duración máxima permitida para una sola incapacidad. */
    private const DIAS_MAXIMOS = 180;

    /**
     * POST /incapacidades
     * Registra una incapacidad en estado "registrada" y calcula los días automáticamente.
     */
    public function store(Request $request, Response $response): Response
    {
        $datos = $this->limpiar((array) ($request->getParsedBody() ?? []));

        $errores = $this->validar($datos);
        if ($errores !== []) {
            return $this->json($response, false, 'Revise los datos del formulario.', ['errores' => $errores], 422);
        }

        $incapacidad = Incapacidad::create($datos + [
            'dias_incapacidad' => Incapacidad::calcularDias($datos['fecha_inicio'], $datos['fecha_fin']),
            'estado'           => 'registrada',
        ]);

        return $this->json(
            $response,
            true,
            'Incapacidad registrada correctamente.',
            $this->conEmpleado(collect([$incapacidad->fresh()]))[0],
            201
        );
    }

    /**
     * Toma solo los campos permitidos y les quita espacios sobrantes.
     */
    private function limpiar(array $entrada): array
    {
        $datos = [];
        foreach ([...array_keys(self::CAMPOS), 'observaciones'] as $campo) {
            $datos[$campo] = trim((string) ($entrada[$campo] ?? ''));
        }
        $datos['observaciones'] = $datos['observaciones'] === '' ? null : $datos['observaciones'];

        return $datos;
    }

    /**
     * Valida los datos de la incapacidad. Devuelve [campo => mensaje] con los errores encontrados.
     * $idActual permite excluir la propia incapacidad al revisar cruces de fechas (edición).
     */
    private function validar(array &$datos, ?int $idActual = null): array
    {
        $errores = [];

        foreach (self::CAMPOS as $campo => $mensaje) {
            if ($datos[$campo] === '') {
                $errores[$campo] = $mensaje;
            }
        }

        foreach (self::LONGITUDES as $campo => $maximo) {
            if (!isset($errores[$campo]) && $datos[$campo] !== null && mb_strlen($datos[$campo]) > $maximo) {
                $errores[$campo] = "Máximo {$maximo} caracteres.";
            }
        }

        if (!isset($errores['tipo']) && !array_key_exists($datos['tipo'], Incapacidad::TIPOS)) {
            $errores['tipo'] = 'El tipo de incapacidad no es válido.';
        }

        // El empleado debe existir y estar activo (consulta de solo lectura a ms-empleados).
        if (!isset($errores['empleado_id'])) {
            if (!ctype_digit($datos['empleado_id'])) {
                $errores['empleado_id'] = 'El empleado no es válido.';
            } else {
                $datos['empleado_id'] = (int) $datos['empleado_id'];
                $empleado = EmpleadoConsulta::find($datos['empleado_id']);

                if ($empleado === null) {
                    $errores['empleado_id'] = 'El empleado no existe.';
                } elseif ($empleado->estado !== 'activo') {
                    $errores['empleado_id'] = 'El empleado está inactivo; no se le pueden registrar incapacidades.';
                }
            }
        }

        // Fechas válidas y en orden.
        foreach (['fecha_inicio' => 'La fecha de inicio', 'fecha_fin' => 'La fecha de fin'] as $campo => $nombre) {
            if (!isset($errores[$campo]) && !$this->fechaValida($datos[$campo])) {
                $errores[$campo] = "{$nombre} no es válida (formato AAAA-MM-DD).";
            } elseif (!isset($errores[$campo]) && $datos[$campo] < '2000-01-01') {
                $errores[$campo] = "{$nombre} no es válida.";
            }
        }

        $fechasValidas = !isset($errores['fecha_inicio']) && !isset($errores['fecha_fin']);

        if ($fechasValidas && $datos['fecha_fin'] < $datos['fecha_inicio']) {
            $errores['fecha_fin'] = 'La fecha de fin no puede ser anterior a la fecha de inicio.';
            $fechasValidas = false;
        }

        if ($fechasValidas && Incapacidad::calcularDias($datos['fecha_inicio'], $datos['fecha_fin']) > self::DIAS_MAXIMOS) {
            $errores['fecha_fin'] = 'Una incapacidad no puede superar ' . self::DIAS_MAXIMOS . ' días.';
            $fechasValidas = false;
        }

        // No se permiten incapacidades del mismo empleado con fechas que se crucen.
        if ($fechasValidas && !isset($errores['empleado_id'])) {
            $cruce = Incapacidad::queSeCruzan($datos['empleado_id'], $datos['fecha_inicio'], $datos['fecha_fin'], $idActual)->first();

            if ($cruce !== null) {
                $errores['fecha_inicio'] = sprintf(
                    'El empleado ya tiene una incapacidad del %s al %s que se cruza con estas fechas.',
                    $cruce->fecha_inicio->format('d/m/Y'),
                    $cruce->fecha_fin->format('d/m/Y')
                );
            }
        }

        return $errores;
    }

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
