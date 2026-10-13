# Sistema de Asistencia

Sistema web para registrar y consultar la asistencia de estudiantes a clases, con roles **administrador** y **estudiante**.

Laravel 13 · PHP 8.3 · MySQL · Blade + Tailwind CSS 4.

## Funcionalidades

**Estudiante** (`/login`, con correo o número de registro)
- Marca su asistencia con un botón solo si hoy es una fecha de clase del módulo activo; se guarda la fecha y hora reales del servidor.
- Una sola marca por fecha de clase; fuera de fecha el botón está deshabilitado.
- Las fechas pasadas sin marca figuran como **Falta**; no puede modificar fechas pasadas.
- Ve todas las fechas del módulo con su estado, la hora de cada marca y un resumen de asistencias y faltas.

**Administrador** (`/admin/login`, con usuario y contraseña propios)
- Lista completa del módulo activo (registro, nombre, correo) con búsqueda por nombre o registro.
- Filtros por fecha o estudiante y totales de presentes y ausentes (por estudiante, por fecha y generales).
- Cambia el estado Presente/Falta de cualquier estudiante en cualquier fecha, incluso pasada.
- Panel **Historial**: acciones del módulo activo (inicios y cierres de sesión, marcas y correcciones) con usuario, fecha, hora, registro afectado y valores anterior → nuevo. Filtrable por estudiante, fecha y tipo de acción.

## Instalación (Laragon en Windows)

1. Clonar en `C:\laragon\www\sistemadeasistencia` y en Laragon: **Start All**.
2. Crear la base de datos `asistencia` (HeidiSQL o `mysql -uroot -e "CREATE DATABASE asistencia"`).
3. En la carpeta del proyecto:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
```

4. Abrir <http://sistemadeasistencia.test> (o ejecutar `php artisan serve` y abrir <http://127.0.0.1:8000>).

La zona horaria se configura con `APP_TIMEZONE` en `.env` (por defecto `America/La_Paz`).

## Datos de prueba (seeder)

| Rol | Usuario | Contraseña |
|---|---|---|
| Administrador | `admin` (o `admin@asistencia.test`) | `admin123` |
| Estudiantes | `2026001` … `2026010` o `estudiante1@asistencia.test` … | `password` |

El seeder crea el módulo activo con clases martes y jueves (4 semanas atrás, hoy y 3 semanas adelante) y asistencias de ejemplo en fechas pasadas. Para reiniciar los datos: `php artisan migrate:fresh --seed`.

## Pruebas

```bash
php artisan test
```

## Estructura principal

| Archivo | Descripción |
|---|---|
| `app/Http/Controllers/AuthController.php` | Login de estudiante / administrador y logout |
| `app/Http/Controllers/Student/AttendanceController.php` | Vista y marca del estudiante |
| `app/Http/Controllers/Admin/AttendanceController.php` | Lista, filtros, totales y correcciones |
| `app/Http/Controllers/Admin/ActivityLogController.php` | Panel de historial |
| `app/Models/Attendance.php` | Estados y regla de Falta automática (`statusFor`) |
| `app/Models/ActivityLog.php` | Registro de actividades (`ActivityLog::record`) |
| `app/Http/Middleware/EnsureRole.php` | Control de acceso por rol (`role:admin`, `role:student`) |
