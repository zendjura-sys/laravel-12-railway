<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnionBlacklistedFamily extends Model
{
    protected $fillable = ['family_name', 'reason', 'duration_hours', 'expires_at', 'added_by'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Реєстрація союзу звіряється з цим — родина в ЧСС, поки бан не спливе.
     *
     * Порівняння — у PHP через mb_strtolower(), НЕ SQL LOWER(): у SQLite
     * (основна БД проєкту) LOWER() лоуркейсить лише ASCII, кириличні назви
     * родин так і лишились би "Family" vs "family" при латиниці, але
     * "ПЕТРО" != "петро" для кирилиці — збіг мовчки не спрацював би.
     */
    public static function isBlacklisted(string $familyName): bool
    {
        $needle = mb_strtolower(trim($familyName));

        return self::query()
            ->active()
            ->get(['family_name'])
            ->contains(fn (self $row) => mb_strtolower($row->family_name) === $needle);
    }
}
