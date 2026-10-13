<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['class_session_id', 'user_id', 'status', 'marked_at', 'updated_by'])]
class Attendance extends Model
{
    use HasFactory;

    public const PRESENT = 'present';

    public const ABSENT = 'absent';

    // Estados calculados cuando todavía no existe registro.
    public const PENDING = 'pending';     // hoy, aún sin marcar

    public const SCHEDULED = 'scheduled'; // fecha futura

    public const LABELS = [
        self::PRESENT => 'Presente',
        self::ABSENT => 'Falta',
        self::PENDING => 'Pendiente',
        self::SCHEDULED => 'Programada',
    ];

    protected function casts(): array
    {
        return [
            'marked_at' => 'datetime',
        ];
    }

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Estado efectivo de un estudiante en una fecha de clase.
     * Sin registro y con la fecha ya terminada => Falta.
     */
    public static function statusFor(ClassSession $session, ?self $attendance): string
    {
        if ($attendance) {
            return $attendance->status;
        }

        if ($session->hasEnded()) {
            return self::ABSENT;
        }

        return $session->isToday() ? self::PENDING : self::SCHEDULED;
    }

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
