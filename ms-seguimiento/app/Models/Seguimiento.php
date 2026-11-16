<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seguimiento extends Model
{
    protected $table = 'seguimientos';

    protected $fillable = ['incapacidad_id', 'fecha', 'comentario', 'estado', 'usuario_responsable'];
}
