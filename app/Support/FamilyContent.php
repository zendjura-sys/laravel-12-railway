<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Содержание родины: должности, направления, критерии роста и структура
 * руководства.
 *
 * Два источника: config/family.php как значения по умолчанию и настройки
 * в БД как правки из админки. Раньше всё это лежало прямо в Home.vue,
 * поэтому смена состава руководства или формулировки должности означала
 * правку кода и деплой — а состав меняется куда чаще, чем выходит релиз.
 *
 * Читают отсюда И сайт, И бот в Telegram: разъехаться им больше негде.
 */
class FamilyContent
{
    private const GROUP = 'content';

    /** Ключи, которые админка может перезаписать, и их форма. */
    private const SHAPES = [
        'positions' => ['title', 'text', 'image'],
        'directions' => ['title', 'tag', 'text', 'image'],
        'leadership' => ['title', 'text', 'nickname'],
    ];

    /** @return array<int,array<string,string>> */
    public static function positions(): array
    {
        return self::list('positions');
    }

    /**
     * Одна должность по индексу — им хранится назначение участника
     * (users.position_index), а не заголовком: если админ переименует или
     * переставит должности в Дизайн → Розділи, привязка участника
     * останется верной.
     *
     * @return array<string,string>|null
     */
    public static function positionAt(?int $index): ?array
    {
        if ($index === null) {
            return null;
        }

        return self::positions()[$index] ?? null;
    }

    /** @return array<int,array<string,string>> */
    public static function directions(): array
    {
        return self::list('directions');
    }

    /** @return array<int,array<string,string>> */
    public static function leadership(): array
    {
        return self::list('leadership');
    }

    /** @return array<int,string> */
    public static function promotionCriteria(): array
    {
        $stored = self::decode('promotion_criteria');

        if ($stored === null) {
            return (array) config('family.promotion_criteria', []);
        }

        return array_values(array_filter(
            array_map(static fn ($v) => trim((string) $v), $stored),
            static fn (string $v) => $v !== '',
        ));
    }

    /** @return array<int,string> */
    public static function about(): array
    {
        $stored = self::decode('about');

        if ($stored === null) {
            return (array) config('family.about', []);
        }

        return array_values(array_filter(
            array_map(static fn ($v) => trim((string) $v), $stored),
            static fn (string $v) => $v !== '',
        ));
    }

    public static function baseCount(): int
    {
        return (int) config('family.base_count', 5);
    }

    /**
     * Сохранение из админки. Каждая запись приводится к форме из SHAPES:
     * так в настройках не оседают лишние ключи, которые пришли из формы.
     *
     * @param  array<int,array<string,mixed>>|array<int,string>  $value
     */
    public static function save(string $key, array $value): void
    {
        if (isset(self::SHAPES[$key])) {
            $fields = self::SHAPES[$key];
            $value = array_values(array_map(
                static fn (array $row) => array_map(
                    static fn (string $f) => trim((string) ($row[$f] ?? '')),
                    array_combine($fields, $fields),
                ),
                array_filter($value, static fn ($row) => is_array($row) && trim((string) ($row['title'] ?? '')) !== ''),
            ));
        } else {
            $value = array_values(array_filter(
                array_map(static fn ($v) => trim((string) $v), $value),
                static fn (string $v) => $v !== '',
            ));
        }

        Setting::set($key, json_encode($value, JSON_UNESCAPED_UNICODE), self::GROUP);
    }

    /** Вернуть ключ к значению из config/family.php. */
    public static function reset(string $key): void
    {
        Setting::set($key, null, self::GROUP);
    }

    /** @return array<int,array<string,string>> */
    private static function list(string $key): array
    {
        $stored = self::decode($key);

        if ($stored === null) {
            return (array) config("family.{$key}", []);
        }

        $fields = self::SHAPES[$key];

        return array_values(array_map(
            static fn (array $row) => array_map(
                static fn (string $f) => (string) ($row[$f] ?? ''),
                array_combine($fields, $fields),
            ),
            array_filter($stored, static fn ($row) => is_array($row)),
        ));
    }

    /**
     * null означает «в админке не трогали» — тогда берём значения из
     * конфига. Пустой массив это НЕ то же самое: им админ мог намеренно
     * очистить список.
     *
     * @return array<int,mixed>|null
     */
    private static function decode(string $key): ?array
    {
        $raw = Setting::get($key);

        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }
}
