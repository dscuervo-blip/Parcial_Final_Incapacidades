<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Vista de solo lectura de la tabla empleados de ms-empleados.
 * Se usa para comprobar que el empleado exista y para mostrar su nombre;
 * este microservicio nunca modifica empleados.
 */
class EmpleadoConsulta extends Model
{
    protected $connection = 'empleados';

    protected $table = 'empleados';

    /**
     * Ids de los empleados cuyo nombre, apellido o documento contienen el texto.
     *
     * @return int[]
     */
    public static function idsQueCoinciden(string $texto): array
    {
        $patron = '%' . $texto . '%';

        return static::query()
            ->where('documento', 'like', $patron)
            ->orWhere('nombres', 'like', $patron)
            ->orWhere('apellidos', 'like', $patron)
            ->orWhereRaw("CONCAT(nombres, ' ', apellidos) LIKE ?", [$patron])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Datos resumidos de varios empleados, indexados por id.
     *
     * @param int[] $ids
     */
    public static function resumenPorIds(array $ids): Collection
    {
        return static::query()
            ->whereIn('id', array_unique($ids))
            ->get(['id', 'nombres', 'apellidos', 'documento', 'area', 'estado'])
            ->keyBy('id');
    }

    /**
     * Formato corto que se adjunta a cada incapacidad.
     */
    public function resumen(): array
    {
        return [
            'id'        => (int) $this->id,
            'nombre'    => trim($this->nombres . ' ' . $this->apellidos),
            'documento' => $this->documento,
            'area'      => $this->area,
            'estado'    => $this->estado,
        ];
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('EmpleadoConsulta es de solo lectura.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('EmpleadoConsulta es de solo lectura.');
    }
}
