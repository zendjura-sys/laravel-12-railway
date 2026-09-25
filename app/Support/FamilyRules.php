<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Розділ «Правила» — один спільний текст для сайту (/rules) і мобільного
 * застосунку (/api/rules), той самий принцип, що й GuideContent.
 *
 * Тексти лежать у resources/rules/*.md у ТОМУ Ж форматі, у якому правила
 * публікує Discord проєкту: "# N. Розділ", "**1.1 Текст пункту**" і блоки
 * ```diff з рядками "- [покарання]" та "! примітка". Оновити правила =
 * вставити свіжий текст із Discord у відповідний файл, без правки коду.
 *
 * Книги: власні правила родини Monsory (kind=family — шкала покарань
 * родини) і правила проєкту Верба Онлайн для довідки (kind=project).
 */
class FamilyRules
{
    /** @return array<int,array{slug:string,title:string,short:string,icon:string,kind:string,file:string,description:string,updatedAt:string}> */
    public static function books(): array
    {
        return [
            [
                'slug' => 'monsory',
                'title' => 'Правила родини Monsory',
                'short' => 'Родина',
                'icon' => '👑',
                'kind' => 'family',
                'file' => 'monsory.md',
                'description' => 'Внутрішні правила родини: зауваження, штрафи, догани, пониження й виключення. Діють разом із правилами проєкту.',
                'updatedAt' => '2026-09-25',
            ],
            [
                'slug' => 'bizwar',
                'title' => 'Правила бізвару',
                'short' => 'Бізвар',
                'icon' => '⚔️',
                'kind' => 'project',
                'file' => 'bizwar.md',
                'description' => 'Правила війни за бізнес проєкту Верба Онлайн. Пункт 2.13 (дистанція на підготовці) скасовано 20.08.2026.',
                'updatedAt' => '2026-08-20',
            ],
            [
                'slug' => 'families',
                'title' => 'Правила сімей',
                'short' => 'Сім\'ї',
                'icon' => '🏛️',
                'kind' => 'project',
                'file' => 'families.md',
                'description' => 'Правила сімей проєкту Верба Онлайн: обов\'язки лідера, поведінка учасників, догани сім\'ї та їх зняття.',
                'updatedAt' => '2026-09-03',
            ],
            [
                'slug' => 'server',
                'title' => 'Правила сервера',
                'short' => 'Сервер',
                'icon' => '🌐',
                'kind' => 'project',
                'file' => 'server.md',
                'description' => 'Загальні правила проєкту Верба Онлайн з урахуванням змін до 24.09.2026. Розділи 4 і 5 — лише ті пункти, що є в нашій копії правил.',
                'updatedAt' => '2026-09-24',
            ],
            [
                'slug' => 'regulations',
                'title' => 'Окремі регламенти',
                'short' => 'Регламенти',
                'icon' => '📌',
                'kind' => 'project',
                'file' => 'regulations.md',
                'description' => 'Зміни з каналу «зміни в правилах», що стосуються лідерів і Військової частини.',
                'updatedAt' => '2026-09-21',
            ],
        ];
    }

    /** Шкала покарань родини — для легенди й кольору бейджів. */
    public static function levels(): array
    {
        return [
            ['key' => 'remark', 'title' => 'Зауваження', 'description' => 'Легке порушення. 3 активні зауваження = 1 догана. Згорає через 7 днів без нових порушень.'],
            ['key' => 'fine', 'title' => 'Штраф', 'description' => 'Грошове стягнення за пунктом правил. Сплатити протягом 48 годин на рахунок, який вкаже керівництво. Несплата — догана й подвоєння суми.'],
            ['key' => 'reprimand', 'title' => 'Догана', 'description' => 'Діє 14 днів. Поки є активна догана — премія за тиждень нараховується на 50%, підвищення неможливе. Максимум 3/3.'],
            ['key' => 'demotion', 'title' => 'Пониження', 'description' => 'Пониження на одну посаду або до Стажера — за порушення, пов\'язані з обов\'язками посади.'],
            ['key' => 'kick', 'title' => 'Виключення', 'description' => 'Виключення з родини: автоматично при 3/3 доганах або одразу за грубі порушення. Повернення — не раніше ніж через 14 днів і лише за рішенням Директора.'],
            ['key' => 'blacklist', 'title' => 'Чорний список', 'description' => 'Виключення без права повернення й внесення до чорного списку родини та Союзу сімей — за злив, шахрайство, зраду.'],
        ];
    }

    /** Повна відповідь для сайту й застосунку. */
    public static function payload(): array
    {
        // Кеш за часом зміни файлів — розбір md на кожен запит не потрібен,
        // а заміна тексту правил одразу скидає кеш без artisan cache:clear.
        $stamp = collect(self::books())->map(fn ($b) => @filemtime(self::path($b['file'])) ?: 0)->implode('-');

        return Cache::rememberForever('family_rules.'.md5($stamp), fn () => [
            'levels' => self::levels(),
            'books' => collect(self::books())->map(fn (array $book) => [
                'slug' => $book['slug'],
                'title' => $book['title'],
                'short' => $book['short'],
                'icon' => $book['icon'],
                'kind' => $book['kind'],
                'description' => $book['description'],
                'updatedAt' => $book['updatedAt'],
                'sections' => self::parse((string) @file_get_contents(self::path($book['file'])), $book['kind']),
            ])->values()->all(),
        ]);
    }

    private static function path(string $file): string
    {
        return resource_path('rules/'.$file);
    }

    /**
     * Розбирає текст у форматі Discord-правил.
     *
     * @return array<int,array{title:string,rules:array<int,array{code:string,text:string,notes:list<string>,penalties:list<array{level:string,text:string}>}>}>
     */
    public static function parse(string $markdown, string $kind): array
    {
        $sections = [];
        $section = null;
        $rule = null;
        $inDiff = false;
        $pendingBold = null;

        $flushRule = function () use (&$rule, &$section) {
            if ($rule !== null && $section !== null) {
                $section['rules'][] = $rule;
            }
            $rule = null;
        };
        $flushSection = function () use (&$section, &$sections, $flushRule) {
            $flushRule();
            if ($section !== null) {
                $sections[] = $section;
            }
            $section = null;
        };

        foreach (preg_split('/\R/u', $markdown) as $raw) {
            $line = trim($raw);

            if (str_starts_with($line, '```')) {
                $inDiff = ! $inDiff;

                continue;
            }

            if ($inDiff) {
                if ($rule === null || $line === '') {
                    continue;
                }
                if (str_starts_with($line, '-')) {
                    $text = trim(trim(ltrim($line, '- ')), '[]');
                    $rule['penalties'][] = ['level' => self::levelFor($text, $kind), 'text' => $text];
                } elseif (str_starts_with($line, '!')) {
                    $rule['notes'][] = trim(ltrim($line, '! '));
                } else {
                    $rule['notes'][] = $line;
                }

                continue;
            }

            // Пункт, що в оригіналі розбитий на кілька рядків усередині **…**.
            if ($pendingBold !== null) {
                $pendingBold .= ' '.$line;
                if (str_ends_with($line, '**')) {
                    $line = $pendingBold;
                    $pendingBold = null;
                } else {
                    continue;
                }
            } elseif (str_starts_with($line, '**') && ! str_ends_with(rtrim($line), '**')) {
                $pendingBold = $line;

                continue;
            }

            if ($line === '') {
                continue;
            }

            if (preg_match('/^#{1,3}\s+(.+)$/u', $line, $m)) {
                $flushSection();
                $section = ['title' => trim($m[1]), 'rules' => []];

                continue;
            }

            if (preg_match('/^\*\*\s*(\d+(?:\.\d+)*)\.?\s+(.+?)\s*\*\*$/us', $line, $m)) {
                $flushRule();
                $section ??= ['title' => 'Загальне', 'rules' => []];
                $rule = ['code' => $m[1], 'text' => trim($m[2]), 'notes' => [], 'penalties' => []];

                continue;
            }

            // Звичайний рядок між пунктами — продовження тексту поточного пункту.
            if ($rule !== null) {
                $rule['text'] .= ' '.trim($line, '* ');
            }
        }

        $flushSection();

        return $sections;
    }

    /**
     * Рівень покарання для кольору бейджа — за найсуворішим ключовим
     * словом у тексті. Для правил проєкту шкала своя: бан/блокування —
     * найважче, решта (Jail, мут, kick, warn, попередження) — "jail".
     */
    public static function levelFor(string $text, string $kind): string
    {
        $t = mb_strtolower($text);

        if ($kind === 'project') {
            foreach (['бан', 'ban', 'блокуван', 'видалення сім', 'конфіскац', 'бізнес', 'розформув'] as $word) {
                if (str_contains($t, $word)) {
                    return 'ban';
                }
            }

            return 'jail';
        }

        $scale = [
            'blacklist' => ['чорний список', 'чс'],
            'kick' => ['виключ'],
            'demotion' => ['пониж', 'зняття з посади'],
            'reprimand' => ['догана', 'догани'],
            'fine' => ['штраф', 'анулювання', 'повернення', 'заборона'],
            'remark' => ['зауваж'],
        ];
        foreach ($scale as $level => $words) {
            foreach ($words as $word) {
                if (str_contains($t, $word)) {
                    return $level;
                }
            }
        }

        return 'remark';
    }
}
