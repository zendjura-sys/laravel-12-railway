<?php

namespace Addons\TelegramBot\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class TelegramLink extends Model
{
    protected $table = 'telegram_links';

    protected $fillable = [
        'user_id', 'chat_id', 'telegram_username', 'link_code', 'code_expires_at', 'linked_at',
    ];

    protected function casts(): array
    {
        return ['code_expires_at' => 'datetime', 'linked_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isLinked(): bool
    {
        return $this->linked_at !== null;
    }

    /**
     * Один рядок на учасника, перевикористовується для будь-якого нового
     * коду — так регенерація ніколи не плодить дублікатів.
     */
    public static function forUser(int $userId): self
    {
        return self::firstOrCreate(['user_id' => $userId]);
    }

    public function generateCode(): string
    {
        $code = strtoupper(Str::random(8));
        $this->update(['link_code' => $code, 'code_expires_at' => now()->addMinutes(10)]);

        return $code;
    }

    public static function findByValidCode(string $code): ?self
    {
        return self::query()
            ->where('link_code', $code)
            ->where('code_expires_at', '>', now())
            ->whereNull('linked_at')
            ->first();
    }
}
