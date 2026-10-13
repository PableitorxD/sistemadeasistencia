<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'code', 'is_active'])]
class Module extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * El módulo activo (el sistema trabaja siempre sobre uno solo).
     */
    public static function active(): ?self
    {
        return static::where('is_active', true)->latest('id')->first();
    }

    public function students(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->where('role', User::ROLE_STUDENT)
            ->withTimestamps();
    }

    public function classSessions(): HasMany
    {
        return $this->hasMany(ClassSession::class)->orderBy('date');
    }
}
