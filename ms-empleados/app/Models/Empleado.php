<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    public const ESTADOS = ['activo', 'inactivo'];

    protected $table = 'empleados';

    protected $fillable = ['nombres', 'apellidos', 'documento', 'correo', 'telefono', 'cargo', 'area', 'fecha_ingreso', 'estado'];

    protected $casts = [
        'fecha_ingreso' => 'date:Y-m-d',
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
     * @param array{documento?: string, area?: string, estado?: string, buscar?: string} $filtros
     */
    public function scopeFiltrar(Builder $query, array $filtros): Builder
    {
        if (!empty($filtros['documento'])) {
            $query->where('documento', 'like', '%' . $filtros['documento'] . '%');
        }

        if (!empty($filtros['area'])) {
            $query->where('area', $filtros['area']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['buscar'])) {
            $texto = '%' . $filtros['buscar'] . '%';
            $query->where(function (Builder $q) use ($texto): void {
                $q->where('nombres', 'like', $texto)
                    ->orWhere('apellidos', 'like', $texto)
                    ->orWhere('correo', 'like', $texto)
                    ->orWhere('cargo', 'like', $texto);
            });
        }

        return $query;
    }
}
