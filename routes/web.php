<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\AttendanceController as AdminAttendanceController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Student\AttendanceController as StudentAttendanceController;
use Illuminate\Support\Facades\Route;

// Inicio: envía a cada usuario a su panel según el rol.
Route::get('/', function () {
    return auth()->user()?->isAdmin()
        ? redirect()->route('admin.attendance.index')
        : redirect()->route('student.attendance.index');
})->middleware('auth')->name('home');

Route::middleware('guest')->group(function () {
    // Estudiantes: correo o número de registro
    Route::get('/login', [AuthController::class, 'showStudentLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'studentLogin'])->name('login.attempt');

    // Administrador: credenciales propias
    Route::get('/admin/login', [AuthController::class, 'showAdminLogin'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'adminLogin'])->name('admin.login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Cambio de contraseña (ambos roles)
Route::middleware('auth')->group(function () {
    Route::get('/cuenta/contrasena', [PasswordController::class, 'edit'])->name('password.edit');
    Route::put('/cuenta/contrasena', [PasswordController::class, 'update'])->name('password.update');
});

Route::middleware(['auth', 'role:student'])->group(function () {
    Route::get('/mi-asistencia', [StudentAttendanceController::class, 'index'])->name('student.attendance.index');
    Route::post('/mi-asistencia/marcar', [StudentAttendanceController::class, 'mark'])->name('student.attendance.mark');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/asistencia', [AdminAttendanceController::class, 'index'])->name('attendance.index');
    Route::put('/asistencia/{classSession}/{student}', [AdminAttendanceController::class, 'update'])->name('attendance.update');

    Route::get('/actividad', [ActivityLogController::class, 'index'])->name('activity.index');
});
