<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Настройки CMS (Telegram, Discord, общие) как key/value в БД, а не в
 * .env — их меняют из админки без доступа к серверу и без редеплоя.
 * .env остаётся источником только для того, что нужно ДО загрузки БД
 * (сама база, APP_KEY и т.п.).
 */
class Setting extends Model
{
    protected $fillable = ['group', 'key', 'value'];

    /** @var array<string, array<string, string|null>>|null */
    private static ?array $cache = null;

    public static function get(string $key, ?string $default = null): ?string
    {
        return self::allValues()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value, string $group = 'general'): void
    {
        self::query()->updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        self::$cache = null;
        Cache::forget('settings.all');
    }

    /** @return array<string, string|null> */
    public static function group(string $group): array
    {
        return self::query()->where('group', $group)->pluck('value', 'key')->all();
    }

    /** @return array<string, string|null> */
    private static function allValues(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            return self::$cache = Cache::remember('settings.all', 3600, function () {
                return self::query()->pluck('value', 'key')->all();
            });
        } catch (\Throwable) {
            return [];
        }
    }
}
