<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Incapacidad extends Model
{
    public const TIPOS = [
        'enfermedad_general'   => 'Enfermedad general',
        'accidente_laboral'    => 'Accidente laboral',
        'licencia_medica'      => 'Licencia médica',
        'incapacidad_temporal' => 'Incapacidad temporal',
    ];

    public const ESTADOS = [
        'registrada'  => 'Registrada',
        'en_revision' => 'En revisión',
        'aprobada'    => 'Aprobada',
        'rechazada'   => 'Rechazada',
        'finalizada'  => 'Finalizada',
    ];

    protected $table = 'incapacidades';

    protected $fillable = ['empleado_id', 'fecha_inicio', 'fecha_fin', 'tipo', 'diagnostico_general', 'entidad_medica', 'observaciones', 'dias_incapacidad', 'estado'];

    protected $casts = [
        'empleado_id'      => 'integer',
        'dias_incapacidad' => 'integer',
        'fecha_inicio'     => 'date:Y-m-d',
        'fecha_fin'        => 'date:Y-m-d',
    ];

    /**
     * Las fechas se devuelven en la hora local (APP_TIMEZONE), sin convertir a UTC.
     */
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Aplica los filtros de búsqueda recibidos por query string.
     *
     * - empleado_ids: lista de ids (ya resuelta a partir del texto buscado).
     * - estado, tipo: coincidencia exacta.
     * - fecha_desde / fecha_hasta: incapacidades que se cruzan con ese rango.
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        if (isset($filtros['empleado_ids'])) {
            $query->whereIn('empleado_id', $filtros['empleado_ids']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', $filtros['tipo']);
        }

        if (!empty($filtros['fecha_desde'])) {
            $query->where('fecha_fin', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->where('fecha_inicio', '<=', $filtros['fecha_hasta']);
        }

        return $query;
    }
}
