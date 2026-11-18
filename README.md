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
