<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UnionBlacklistedPlayer extends Model
{
    protected $fillable = ['first_name', 'last_name', 'family_name', 'reasons', 'description', 'added_by'];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
        ];
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }

    /**
     * Реєстрація (і союзу, і основного сайту) звіряється з цим за
     * ім'ям+прізвищем, без урахування регістру.
     *
     * Порівняння — у PHP через mb_strtolower(), НЕ SQL LOWER(): у SQLite
     * LOWER() лоуркейсить лише ASCII, кириличні імена ("Петро" vs "петро")
     * так і не збіглися б.
     */
    public static function isBlacklisted(string $firstName, ?string $lastName): bool
    {
        $first = mb_strtolower(trim($firstName));
        $last = mb_strtolower(trim((string) $lastName));

        return self::query()
            ->get(['first_name', 'last_name'])
            ->contains(fn (self $row) => mb_strtolower($row->first_name) === $first
                && mb_strtolower((string) $row->last_name) === $last);
    }
}
