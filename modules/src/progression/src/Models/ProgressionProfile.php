<?php

namespace Addons\Progression\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProgressionProfile extends Model
{
    protected $table = 'progression_profiles';

    protected $fillable = [
        'user_id', 'xp', 'current_streak', 'longest_streak',
        'kapt_wins', 'kapt_losses', 'contracts_count', 'heavy_contracts_count', 'last_activity_at',
    ];

    // Явные PHP-дефолты обязательны: firstOrCreate(['user_id' => ...]) без
    // второго аргумента не подтягивает DB-DEFAULT-значения колонок в
    // атрибуты модели — в памяти они остаются null, а не 0, пока объект не
    // перечитан из БД. `null === 0` строго ложно, и именно это ломало
    // проверку "это первая победа" в ProgressionService.
    protected $attributes = [
        'xp' => 0,
        'current_streak' => 0,
        'longest_streak' => 0,
        'kapt_wins' => 0,
        'kapt_losses' => 0,
        'contracts_count' => 0,
        'heavy_contracts_count' => 0,
    ];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** floor(sqrt(XP / 250)) + 1 — формула фиксирована брифом. */
    public function level(): int
    {
        return (int) floor(sqrt($this->xp / 250)) + 1;
    }

    /** Ranks: Recruit lv1, Fighter lv3, Senior Fighter lv5, Veteran lv7, Elite lv9, Officer lv12. */
    public function rank(): string
    {
        $level = $this->level();

        return match (true) {
            $level >= 12 => 'Officer',
            $level >= 9 => 'Elite',
            $level >= 7 => 'Veteran',
            $level >= 5 => 'Senior Fighter',
            $level >= 3 => 'Fighter',
            default => 'Recruit',
        };
    }
}
