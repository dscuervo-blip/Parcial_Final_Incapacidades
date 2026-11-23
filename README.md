# Parcial_Final_Incapacidades — Backend

Backend del Sistema Web de Gestión de Incapacidades Médicas, construido con microservicios en PHP 8 + Slim 4 + Eloquent ORM.

| Microservicio | Puerto | Base de datos | Responsabilidad |
|---|---|---|---|
| ms-auth | 8001 | ms-auth | Autenticación |
| ms-empleados | 8002 | ms-empleados | Gestión de empleados |
| ms-incapacidades | 8003 | ms-incapacidades | Gestión de incapacidades |
| ms-seguimiento | 8004 | ms-seguimiento | Seguimiento y trazabilidad |

## Instalación

En cada microservicio:

```bash
composer install
cp .env.example .env
```

## Ejecución

Desde la raíz del repositorio, una terminal por servicio:

```bash
php -S 127.0.0.1:8001 -t ms-auth/public
php -S 127.0.0.1:8002 -t ms-empleados/public
php -S 127.0.0.1:8003 -t ms-incapacidades/public
php -S 127.0.0.1:8004 -t ms-seguimiento/public
```

`GET /` en cada servicio indica si está funcionando y si conecta a su base de datos.

## Autenticación

1. `POST /login` en ms-auth devuelve un token y marca `sesion_activa = 1`.
2. Las rutas protegidas exigen el header `Authorization: Bearer <token>`.
3. `AuthMiddleware` (presente en los 4 servicios) valida que el token exista, que la sesión esté activa y que el usuario esté activo. Puede restringir por rol: `new AuthMiddleware(['administrador'])`.
4. `POST /logout` invalida el token.

Los microservicios no se comunican entre sí. Para validar el token, cada uno abre una conexión **de solo lectura** (`auth`, variables `AUTH_DB_*` del `.env`) a la base de datos de ms-auth.

Todas las respuestas, incluidos los errores, usan el formato:

```json
{ "ok": true, "mensaje": "Texto descriptivo", "data": {} }
```

## Endpoints

### ms-empleados (puerto 8002)

Requieren token de un usuario con rol `administrador` o `gestion_humana`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/empleados` | Lista empleados. Filtros opcionales: `documento` (coincidencia parcial), `area`, `estado` (`activo`/`inactivo`), `buscar` (nombre, apellido, correo o cargo). |
| GET | `/empleados/areas` | Áreas registradas, para llenar filtros. |
| GET | `/empleados/{id}` | Detalle de un empleado. 404 si no existe. |
| POST | `/empleados` | Registra un empleado. Responde 201. |
| PUT | `/empleados/{id}` | Actualiza información personal, cargo, área y estado. |
| PATCH | `/empleados/{id}/estado` | Activa o inactiva al empleado. Cuerpo: `{"estado": "inactivo"}`. 409 si ya tiene ese estado. |

Ejemplo: `GET /empleados?area=Tecnologia&estado=activo`

Cuerpo de `POST` / `PUT` (JSON):

```json
{
  "nombres": "Ana", "apellidos": "Ruiz", "documento": "55443322",
  "correo": "ana@empresa.com", "telefono": "3105550000",
  "cargo": "Analista", "area": "Calidad", "fecha_ingreso": "2025-03-10",
  "estado": "activo"
}
```

Validaciones: todos los campos son obligatorios (salvo `estado`, que por defecto es `activo`); el documento solo admite números (mínimo 5 dígitos) y no puede repetirse; el correo debe ser válido y no puede repetirse; el teléfono admite de 7 a 20 dígitos; la fecha de ingreso debe ser válida y no futura. Los errores se devuelven con código 422 y el detalle por campo en `data.errores`.

### ms-incapacidades (puerto 8003)

Requieren token de un usuario con rol `administrador` o `gestion_humana`.

| Método | Ruta | Descripción |
|---|---|---|
| GET | `/incapacidades` | Lista incapacidades (las más recientes primero), con los datos básicos del empleado. |
| GET | `/incapacidades/catalogos` | Tipos y estados permitidos, para llenar filtros y formularios. |
| GET | `/incapacidades/{id}` | Detalle de una incapacidad. 404 si no existe. |

Filtros opcionales de `GET /incapacidades`:

| Parámetro | Descripción |
|---|---|
| `empleado_id` | Id exacto del empleado. |
| `empleado` | Nombre, apellido o documento del empleado (coincidencia parcial). |
| `estado` | `registrada`, `en_revision`, `aprobada`, `rechazada` o `finalizada`. |
| `tipo` | `enfermedad_general`, `accidente_laboral`, `licencia_medica` o `incapacidad_temporal`. |
| `fecha_desde`, `fecha_hasta` | Incapacidades que se cruzan con ese rango (formato `AAAA-MM-DD`). |

Ejemplo: `GET /incapacidades?empleado=laura&estado=en_revision`

Para mostrar el nombre del empleado y buscar por él, ms-incapacidades usa una conexión **de solo lectura** (`empleados`, variables `EMPLEADOS_DB_*`) a la base de datos de ms-empleados. Nunca modifica esa base de datos.
