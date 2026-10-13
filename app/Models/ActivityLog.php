<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'module_id', 'action', 'description', 'student_id',
    'class_session_id', 'attendance_id', 'old_values', 'new_values', 'ip_address',
])]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    public const LOGIN = 'login';

    public const LOGIN_FAILED = 'login_failed';

    public const LOGOUT = 'logout';

    public const ATTENDANCE_MARKED = 'attendance_marked';

    public const ATTENDANCE_UPDATED = 'attendance_updated';

    public const PASSWORD_CHANGED = 'password_changed';

    public const LABELS = [
        self::LOGIN => 'Inicio de sesión',
        self::LOGIN_FAILED => 'Intento de acceso fallido',
        self::LOGOUT => 'Cierre de sesión',
        self::ATTENDANCE_MARKED => 'Marca de asistencia',
        self::ATTENDANCE_UPDATED => 'Corrección de asistencia',
        self::PASSWORD_CHANGED => 'Cambio de contraseña',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Guarda una acción en el historial. El usuario, la IP y el módulo
     * activo se toman del contexto actual si no se indican.
     *
     * @param  array<string, mixed>  $attributes
     */
    public static function record(string $action, string $description, array $attributes = []): self
    {
        return static::create(array_merge([
            'user_id' => auth()->id(),
            'module_id' => Module::active()?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()?->ip(),
        ], $attributes));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function actionLabel(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
