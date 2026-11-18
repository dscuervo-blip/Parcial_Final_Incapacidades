<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Vista de solo lectura de la tabla usuarios de ms-auth.
 * Se usa únicamente para validar el token de sesión en AuthMiddleware.
 */
class SesionUsuario extends Model
{
    protected $connection = 'auth';

    protected $table = 'usuarios';

    protected $hidden = ['contrasena', 'token'];

    /**
     * Busca el usuario dueño de un token con sesión activa y estado activo.
     */
    public static function porTokenActivo(string $token): ?self
    {
        return static::where('token', $token)
            ->where('sesion_activa', 1)
            ->where('estado', 'activo')
            ->first();
    }

    public function save(array $options = []): bool
    {
        throw new LogicException('SesionUsuario es de solo lectura.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('SesionUsuario es de solo lectura.');
    }
}
