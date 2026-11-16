<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $table = 'empleados';

    protected $fillable = ['nombres', 'apellidos', 'documento', 'correo', 'telefono', 'cargo', 'area', 'fecha_ingreso', 'estado'];
}
