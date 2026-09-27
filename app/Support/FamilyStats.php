<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Загальна статистика родини — реальні числа з БД, а не текст, який
 * вписує адмін вручну. Частина метрик залежить від опційного модуля
 * (Reports/Bonuses/Family Goals) — Schema::hasTable() рятує, якщо той
 * не встановлений, метрика просто зникає зі списку доступних (той самий
 * підхід, що й у AddonAutoloader/unreadNotificationsCount): ядро ніде
 * не імпортує класи аддонів, лише читає їхні таблиці напряму.
 *
 * Адмін обирає, ЯКІ з доступних метрик показувати на головній сторінці
 * (Setting 'home_stats', group 'content') — ключі й порядок зберігаються.
 */
class FamilyStats
{
    private const GROUP = 'content';
    private const SETTING_KEY = 'home_stats';

    private const DEFAULT_KEYS = ['members', 'reports_total', 'contracts_total', 'bonuses_paid'];

    /** @return array<int,array{key:string,label:string,value:int}> Усі метрики, доступні на цьому сайті (потрібний модуль встановлено). */
    public static function available(): array
    {
        $result = [];
        foreach (self::definitions() as $key => $def) {
            $resolved = self::resolve($key, $def);
            if ($resolved !== null) {
                $result[] = $resolved;
            }
        }

        return $result;
    }

    /** @return array<int,array{key:string,label:string,value:int}> Обрані адміном для показу на головній, у порядку вибору. */
    public static function selected(): array
    {
        $keys = self::selectedKeys();
        $all = collect(self::available())->keyBy('key');

        return collect($keys)
            ->map(fn (string $key) => $all->get($key))
            ->filter()
            ->values()
            ->all();
    }

    /** @return array<int,string> */
    public static function selectedKeys(): array
    {
        $available = array_column(self::available(), 'key');
        $raw = Setting::get(self::SETTING_KEY);

        if ($raw === null || trim($raw) === '') {
            return array_values(array_intersect(self::DEFAULT_KEYS, $available));
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return array_values(array_intersect(self::DEFAULT_KEYS, $available));
        }

        return array_values(array_intersect($decoded, $available));
    }

    /** @param array<int,string> $keys */
    public static function save(array $keys): void
    {
        $available = array_column(self::available(), 'key');
        $keys = array_values(array_intersect($keys, $available));

        Setting::set(self::SETTING_KEY, json_encode($keys), self::GROUP);
    }

    /** @param array{label:string,requires?:string,value:callable():int} $def */
    private static function resolve(string $key, array $def): ?array
    {
        if (isset($def['requires']) && ! Schema::hasTable($def['requires'])) {
            return null;
        }

        return ['key' => $key, 'label' => $def['label'], 'value' => (int) ($def['value'])()];
    }

    /** @return array<string,array{label:string,requires?:string,value:callable():int}> */
    private static function definitions(): array
    {
        return [
            'members' => [
                'label' => 'Учасників родини',
                'value' => fn () => User::query()->whereNull('union_family_name')->count(),
            ],
            'reports_total' => [
                'label' => 'Подано звітів',
                'requires' => 'reports',
                'value' => fn () => DB::table('reports')->where('status', 'approved')->count(),
            ],
            'bizwar_wins' => [
                'label' => 'Перемог у бізварі',
                'requires' => 'reports',
                'value' => fn () => (int) DB::table('reports')
                    ->where('status', 'approved')->where('type', 'bizwar')
                    ->sum('wins_count'),
            ],
            'contracts_total' => [
                'label' => 'Виконано контрактів',
                'requires' => 'reports',
                'value' => fn () => (int) DB::table('reports')
                    ->where('status', 'approved')->where('type', 'contract')
                    ->selectRaw('COALESCE(SUM(light_count),0) + COALESCE(SUM(medium_count),0) + COALESCE(SUM(heavy_count),0) as total')
                    ->value('total'),
            ],
            'investment_total' => [
                'label' => 'Інвестовано, ₴',
                'requires' => 'reports',
                'value' => fn () => (int) DB::table('reports')
                    ->where('status', 'approved')->where('type', 'investment')
                    ->sum('amount'),
            ],
            'bonuses_paid' => [
                'label' => 'Виплачено премій, ₴',
                'requires' => 'bonus_payouts',
                'value' => fn () => (int) DB::table('bonus_payouts')->where('paid', true)->sum('total_amount'),
            ],
            'goals_completed' => [
                'label' => 'Досягнуто цілей родини',
                'requires' => 'family_goals',
                'value' => fn () => DB::table('family_goals')->where('status', 'completed')->count(),
            ],
            'union_families' => [
                'label' => 'Родин у союзі',
                'value' => fn () => User::query()->whereNotNull('union_family_name')->distinct('union_family_name')->count('union_family_name'),
            ],
            'union_members' => [
                'label' => 'Союзників',
                'value' => fn () => User::query()->whereNotNull('union_family_name')->count(),
            ],
        ];
    }
}
