<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

/**
 * Una fecha de clase programada dentro de un módulo.
 */
#[Fillable(['module_id', 'date', 'topic'])]
class ClassSession extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function isToday(): bool
    {
        return $this->date->isSameDay(today());
    }

    /**
     * La fecha de clase ya terminó (día anterior a hoy).
     */
    public function hasEnded(): bool
    {
        return $this->date->lt(today());
    }
}
