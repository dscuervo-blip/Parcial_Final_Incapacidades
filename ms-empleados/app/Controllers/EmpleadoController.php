<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Empleado;
use DateTimeImmutable;
use Illuminate\Database\QueryException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EmpleadoController extends Controller
{
    /** Campos obligatorios y el mensaje cuando faltan. */
    private const CAMPOS = [
        'nombres'       => 'Los nombres son obligatorios.',
        'apellidos'     => 'Los apellidos son obligatorios.',
        'documento'     => 'El documento es obligatorio.',
        'correo'        => 'El correo es obligatorio.',
        'telefono'      => 'El teléfono es obligatorio.',
        'cargo'         => 'El cargo es obligatorio.',
        'area'          => 'El área es obligatoria.',
        'fecha_ingreso' => 'La fecha de ingreso es obligatoria.',
    ];

    /** Longitud máxima según la tabla empleados. */
    private const LONGITUDES = [
        'nombres'   => 100,
        'apellidos' => 100,
        'documento' => 30,
        'correo'    => 150,
        'telefono'  => 30,
        'cargo'     => 100,
        'area'      => 100,
    ];

    /**
     * POST /empleados
     */
    public function store(Request $request, Response $response): Response
    {
        $datos = $this->limpiar((array) ($request->getParsedBody() ?? []));
        $datos['estado'] = $datos['estado'] ?: 'activo';

        $errores = $this->validar($datos);
        if ($errores !== []) {
            return $this->json($response, false, 'Revise los datos del formulario.', ['errores' => $errores], 422);
        }

        try {
            $empleado = Empleado::create($datos);
        } catch (QueryException $e) {
            return $this->duplicado($response, $e);
        }

        return $this->json($response, true, 'Empleado registrado correctamente.', $empleado->fresh(), 201);
    }

    /**
     * PUT /empleados/{id}
     * Actualiza información personal, cargo, área y estado.
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $empleado = Empleado::find((int) $args['id']);

        if ($empleado === null) {
            return $this->json($response, false, 'El empleado no existe.', null, 404);
        }

        $datos = $this->limpiar((array) ($request->getParsedBody() ?? []));
        $datos['estado'] = $datos['estado'] ?: $empleado->estado;

        $errores = $this->validar($datos, $empleado->id);
        if ($errores !== []) {
            return $this->json($response, false, 'Revise los datos del formulario.', ['errores' => $errores], 422);
        }

        try {
            $empleado->update($datos);
        } catch (QueryException $e) {
            return $this->duplicado($response, $e);
        }

        return $this->json($response, true, 'Empleado actualizado correctamente.', $empleado->fresh());
    }

    /**
     * Toma solo los campos permitidos y les quita espacios sobrantes.
     */
    private function limpiar(array $entrada): array
    {
        $datos = [];
        foreach ([...array_keys(self::CAMPOS), 'estado'] as $campo) {
            $datos[$campo] = trim((string) ($entrada[$campo] ?? ''));
        }
        $datos['correo'] = mb_strtolower($datos['correo']);

        return $datos;
    }

    /**
     * Valida los datos del empleado. Devuelve [campo => mensaje] con los errores encontrados.
     */
    private function validar(array $datos, ?int $idActual = null): array
    {
        $errores = [];

        foreach (self::CAMPOS as $campo => $mensaje) {
            if ($datos[$campo] === '') {
                $errores[$campo] = $mensaje;
            }
        }

        foreach (self::LONGITUDES as $campo => $maximo) {
            if (!isset($errores[$campo]) && mb_strlen($datos[$campo]) > $maximo) {
                $errores[$campo] = "Máximo {$maximo} caracteres.";
            }
        }

        if (!isset($errores['documento']) && !preg_match('/^\d{5,30}$/', $datos['documento'])) {
            $errores['documento'] = 'El documento debe tener solo números (mínimo 5 dígitos).';
        }

        if (!isset($errores['correo']) && filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['correo'] = 'El correo no tiene un formato válido.';
        }

        if (!isset($errores['telefono']) && !preg_match('/^\+?[\d\s-]{7,20}$/', $datos['telefono'])) {
            $errores['telefono'] = 'El teléfono debe tener entre 7 y 20 dígitos.';
        }

        if (!isset($errores['fecha_ingreso'])) {
            $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha_ingreso']);
            if ($fecha === false || $fecha->format('Y-m-d') !== $datos['fecha_ingreso']) {
                $errores['fecha_ingreso'] = 'La fecha de ingreso no es válida (formato AAAA-MM-DD).';
            } elseif ($fecha > new DateTimeImmutable('today')) {
                $errores['fecha_ingreso'] = 'La fecha de ingreso no puede ser futura.';
            } elseif ($fecha < new DateTimeImmutable('1950-01-01')) {
                $errores['fecha_ingreso'] = 'La fecha de ingreso no es válida.';
            }
        }

        if (!in_array($datos['estado'], Empleado::ESTADOS, true)) {
            $errores['estado'] = 'El estado debe ser "activo" o "inactivo".';
        }

        // Documento y correo únicos (sin contar al propio empleado al editar).
        foreach (['documento' => 'Ya existe un empleado con ese documento.', 'correo' => 'Ya existe un empleado con ese correo.'] as $campo => $mensaje) {
            if (isset($errores[$campo])) {
                continue;
            }
            $existe = Empleado::where($campo, $datos[$campo])
                ->when($idActual !== null, fn ($q) => $q->where('id', '!=', $idActual))
                ->exists();
            if ($existe) {
                $errores[$campo] = $mensaje;
            }
        }

        return $errores;
    }

    /**
     * Respaldo por si dos registros iguales llegan al mismo tiempo (índice UNIQUE de la tabla).
     */
    private function duplicado(Response $response, QueryException $e): Response
    {
        if (($e->errorInfo[1] ?? null) === 1062) {
            return $this->json($response, false, 'El documento o el correo ya están registrados.', null, 409);
        }

        throw $e;
    }

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
