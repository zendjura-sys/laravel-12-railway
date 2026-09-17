<?php

namespace Addons\TelegramBot\Bot;

use Addons\TelegramBot\Models\TelegramApplication;
use Addons\TelegramBot\Models\TelegramChat;
use App\Support\FamilyContent;

/**
 * Все экраны бота в одном месте: текст + клавиатура для каждого.
 *
 * Бот управляется только кнопками — команд нет ни одной. Поэтому каждый
 * экран обязан сам предлагать, куда идти дальше: тупик без кнопки
 * «назад» в таком интерфейсе означает, что человек застрял и вынужден
 * перезапускать диалог.
 *
 * Содержание (должности, направления, критерии, структура) берётся через
 * FamilyContent — из того же источника, что и сайт: правки в админке или
 * значения по умолчанию из config/family.php.
 */
class Screens
{
    private const RULE = '━━━━━━━━━━━━━━━';

    /** Варианты ответов анкеты. Индекс в callback_data, текст — людям. */
    public const AGES = ['14–17', '18–20', '21–25', '26+'];
    public const PLAYTIME = ['до 2 год/день', '2–4 год/день', '4–6 год/день', '6+ год/день'];
    public const EXPERIENCE = ['Новачок', 'До року', '1–3 роки', 'Понад 3 роки'];
    public const DIRECTIONS = ['Бізнес-війни', 'Контракти', 'Ще не визначився'];

    /**
     * @return array{text:string,keyboard:array<string,mixed>}
     */
    public function home(TelegramChat $chat, ?string $memberName, ?string $positionTitle = null): array
    {
        if ($memberName !== null) {
            $text = $this->head('MONSORY FAMILY')
                ."Вітаємо, <b>".e($memberName)."</b>.\n"
                ."Ваш акаунт привʼязано до цього чату.\n";

            if ($positionTitle !== null) {
                $text .= 'Посада: <b>'.e($positionTitle)."</b>\n";
            }

            $text .= "\n".'Оберіть розділ:';

            $rows = [
                [$this->btn('👤  Мій акаунт', 'account'), $this->btn('📊  Статистика', 'stats')],
                [$this->btn('🏛  Про родину', 'about'), $this->btn('💼  Посади', 'positions')],
                [$this->btn('⚔️  Напрямки', 'directions'), $this->btn('📈  Як рости', 'growth')],
                [$this->btn('👥  Структура', 'structure')],
            ];

            if ($site = $this->siteUrl()) {
                $rows[] = [['text' => '📝  Подати звіт', 'url' => rtrim($site, '/').'/reports']];
            }
        } else {
            $text = $this->head('MONSORY FAMILY')
                ."<i>Закрите коло, де кожного знають в обличчя.</i>\n\n"
                ."Спільний особняк і автопарк, свій звʼязок, спільні операції\n"
                ."та власний кодекс. Ми беремо небагатьох.\n\n"
                .'Оберіть розділ:';

            $rows = [
                [$this->btn('📝  Подати заявку', 'apply')],
                [$this->btn('🏛  Про родину', 'about'), $this->btn('💼  Посади', 'positions')],
                [$this->btn('⚔️  Напрямки', 'directions'), $this->btn('📈  Як рости', 'growth')],
                [$this->btn('👥  Структура', 'structure'), $this->btn('🔗  Мій акаунт', 'account')],
            ];
        }

        if ($site = $this->siteUrl()) {
            $rows[] = [['text' => '🌐  Сайт родини', 'url' => $site]];
        }

        return $this->screen($text, $rows);
    }

    public function about(): array
    {
        $body = '';
        foreach (FamilyContent::about() as $paragraph) {
            $body .= e($paragraph)."\n\n";
        }

        return $this->screen(
            $this->head('🏛  ПРО РОДИНУ').$body.self::RULE,
            [
                [$this->btn('💼  Посади', 'positions'), $this->btn('⚔️  Напрямки', 'directions')],
                [$this->btn('📝  Подати заявку', 'apply')],
                [$this->backHome()],
            ]
        );
    }

    public function directions(): array
    {
        $body = '';
        foreach (FamilyContent::directions() as $d) {
            $body .= '◆ <b>'.e($d['title'])."</b>\n"
                .'<i>'.e($d['tag'])."</i>\n"
                .e($d['text'])."\n\n";
        }

        return $this->screen(
            $this->head('⚔️  НАПРЯМКИ').$body.self::RULE,
            [
                [$this->btn('💼  Посади', 'positions'), $this->btn('📈  Як рости', 'growth')],
                [$this->backHome()],
            ]
        );
    }

    /** Список всех должностей: номер + название, по две в ряд. */
    public function positions(): array
    {
        $positions = FamilyContent::positions();
        $baseCount = FamilyContent::baseCount();

        $text = $this->head('💼  ПОСАДИ')
            ."Десять посад, суворо за порядком росту.\n"
            .'Перші '.$baseCount." — основний склад, далі — напрямки\nта керівництво.\n\n"
            .'Оберіть посаду, щоб прочитати деталі:';

        $rows = [];
        $buffer = [];
        foreach ($positions as $i => $p) {
            $buffer[] = $this->btn(sprintf('%02d  %s', $i + 1, $p['title']), 'pos:'.$i);
            if (count($buffer) === 2) {
                $rows[] = $buffer;
                $buffer = [];
            }
        }
        if ($buffer) {
            $rows[] = $buffer;
        }

        $rows[] = [$this->backHome()];

        return $this->screen($text, $rows);
    }

    /** Карточка одной должности с переходами к соседним. */
    public function position(int $index): array
    {
        $positions = FamilyContent::positions();
        $total = count($positions);
        $index = max(0, min($index, $total - 1));
        $p = $positions[$index];

        $text = $this->head(sprintf('ПОСАДА %02d / %02d', $index + 1, $total))
            .'<b>'.e($p['title'])."</b>\n\n"
            .e($p['text'])."\n\n"
            .self::RULE;

        // Листалка по кругу: с последней должности «наступна» возвращает к
        // первой, иначе на краях списка кнопка просто пропадала бы и ряд
        // прыгал по ширине.
        $prev = ($index - 1 + $total) % $total;
        $next = ($index + 1) % $total;

        return $this->screen($text, [
            [$this->btn('◀️  '.$positions[$prev]['title'], 'pos:'.$prev)],
            [$this->btn($positions[$next]['title'].'  ▶️', 'pos:'.$next)],
            [$this->btn('↩️  Усі посади', 'positions'), $this->backHome()],
        ]);
    }

    /** Хто за що відповідає — з ніками, якщо їх заповнили в адмінці. */
    public function structure(): array
    {
        $units = FamilyContent::leadership();

        if ($units === []) {
            return $this->screen(
                $this->head('👥  СТРУКТУРА')."Структуру ще не заповнено.\n\n".self::RULE,
                [[$this->backHome()]]
            );
        }

        $text = $this->head('👥  ХТО ЗА ЩО ВІДПОВІДАЄ')
            ."Кожен напрямок має відповідального.\nПитання — спершу до нього.\n\n";

        foreach ($units as $u) {
            $text .= '◆ <b>'.e($u['title'])."</b>\n";
            if (trim((string) ($u['nickname'] ?? '')) !== '') {
                $text .= '<code>'.e($u['nickname'])."</code>\n";
            }
            if (trim((string) ($u['text'] ?? '')) !== '') {
                $text .= e($u['text'])."\n";
            }
            $text .= "\n";
        }

        return $this->screen($text.self::RULE, [
            [$this->btn('💼  Посади', 'positions'), $this->btn('📈  Як рости', 'growth')],
            [$this->backHome()],
        ]);
    }

    public function growth(): array
    {
        $text = $this->head('📈  ЯК РОСТИ')
            ."Підвищення — не за вислугу років, а за внесок.\nДивимось на це:\n\n";

        foreach (FamilyContent::promotionCriteria() as $c) {
            $text .= '  ◆  '.e($c)."\n";
        }

        $text .= "\nПочинають усі однаково — зі <b>Стажера</b>.\nДалі все залежить від вас.\n\n".self::RULE;

        return $this->screen($text, [
            [$this->btn('💼  Посади', 'positions')],
            [$this->btn('📝  Подати заявку', 'apply')],
            [$this->backHome()],
        ]);
    }

    /* ==================== АККАУНТ ==================== */

    public function account(?string $memberName, ?string $memberEmail, ?string $positionTitle = null): array
    {
        if ($memberName !== null) {
            $text = $this->head('👤  МІЙ АКАУНТ')
                ."Чат привʼязано до акаунту на сайті.\n\n"
                .'<b>'.e($memberName)."</b>\n"
                .'<code>'.e((string) $memberEmail)."</code>\n";

            $text .= $positionTitle !== null
                ? 'Посада: <b>'.e($positionTitle)."</b>\n\n".self::RULE
                : "Посаду ще не призначено.\n\n".self::RULE;

            $rows = [[$this->btn('🔓  Відвʼязати акаунт', 'unlink')]];
        } else {
            $text = $this->head('🔗  МІЙ АКАУНТ')
                ."Цей чат ще не привʼязаний до акаунту на сайті.\n\n"
                ."Привʼязка потрібна, щоб отримувати сюди сповіщення\n"
                ."та бачити свій статус у родині.\n\n"
                ."<b>Як привʼязати:</b>\n"
                ."  1. Відкрийте сайт → Профіль → Telegram\n"
                ."  2. Натисніть «Привʼязати Telegram»\n"
                ."  3. Сайт сам відкриє цей чат — жодних кодів вручну\n\n"
                .self::RULE;

            $rows = [];
            if ($site = $this->siteUrl()) {
                $rows[] = [['text' => '🌐  Відкрити профіль на сайті', 'url' => rtrim($site, '/').'/profile']];
            }
        }

        $rows[] = [$this->backHome()];

        return $this->screen($text, $rows);
    }

    /**
     * "Що вже нарахувало" — прогресія (якщо встановлено Progression) і
     * живий (не збережений) розрахунок премії поточного тижня (якщо
     * встановлено Bonuses). Обидва блоки опційні: чого з двох модулів
     * немає — той блок просто не показується, без помилки.
     *
     * @param  array{
     *     progression?: array{level:int,xp:int,streak:int,achievements:int},
     *     bonus?: array{bizwar_amount:int,winrate:?float,contract_amount:int,contracts_count:int,streak_bonus_amount:int,contracts_count_bonus_amount:int,total_amount:int},
     *     investment?: array{cumulative:int,next_tier_label:?string,next_tier_left:?int},
     * }|null  $data  null — чат ще не привʼязано до акаунту
     */
    public function stats(?array $data): array
    {
        if ($data === null) {
            $text = $this->head('📊  СТАТИСТИКА')
                ."Статистика доступна лише для привʼязаного акаунту.\n\n"
                .self::RULE;

            $rows = [];
            if ($site = $this->siteUrl()) {
                $rows[] = [['text' => '🌐  Відкрити профіль на сайті', 'url' => rtrim($site, '/').'/profile']];
            }
            $rows[] = [$this->backHome()];

            return $this->screen($text, $rows);
        }

        $text = $this->head('📊  СТАТИСТИКА');

        if ($progression = $data['progression'] ?? null) {
            $text .= "<b>Прогресія</b>\n"
                ."Рівень: {$progression['level']} ({$progression['xp']} XP)\n"
                ."Серія перемог: {$progression['streak']}\n"
                ."Ачівки: {$progression['achievements']}\n\n";
        }

        if ($bonus = $data['bonus'] ?? null) {
            $text .= "<b>Премія цього тижня</b>  <i>(поточний розрахунок)</i>\n";
            if ($bonus['bizwar_amount'] > 0) {
                $text .= "Бізвар: {$this->money($bonus['bizwar_amount'])}";
                $text .= $bonus['winrate'] !== null ? " ({$bonus['winrate']}% winrate)\n" : "\n";
            }
            if ($bonus['contract_amount'] > 0) {
                $text .= "Контракти: {$this->money($bonus['contract_amount'])} (×{$bonus['contracts_count']})\n";
            }
            $extraBonuses = $bonus['streak_bonus_amount'] + $bonus['contracts_count_bonus_amount'];
            if ($extraBonuses > 0) {
                $text .= "Додаткові бонуси: {$this->money($extraBonuses)}\n";
            }
            $text .= "<b>Разом: {$this->money($bonus['total_amount'])}</b>\n\n";
        }

        if ($investment = $data['investment'] ?? null) {
            $text .= "<b>Інвестиції</b>\n"
                ."Вкладено: {$this->money($investment['cumulative'])}\n";
            if ($investment['next_tier_label'] !== null) {
                $text .= 'До тіру «'.e($investment['next_tier_label'])."»: ще {$this->money($investment['next_tier_left'])}\n";
            }
            $text .= "\n";
        }

        if (! ($data['progression'] ?? null) && ! ($data['bonus'] ?? null) && ! ($data['investment'] ?? null)) {
            $text .= "Даних поки немає.\n\n";
        }

        $text .= self::RULE;

        $rows = [];
        if ($site = $this->siteUrl()) {
            $rows[] = [['text' => '📝  Подати звіт', 'url' => rtrim($site, '/').'/reports']];
        }
        $rows[] = [$this->backHome()];

        return $this->screen($text, $rows);
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').'₴';
    }

    /* ==================== ЗАЯВКА ==================== */

    /**
     * Заявку принимаем только от привязанного аккаунта.
     *
     * Иначе после одобрения некого впускать: заявка живёт отдельно от
     * профиля на сайте, и связать её с человеком можно было бы разве что
     * вручную по нику.
     */
    public function applyNeedsAccount(): array
    {
        $text = $this->head('🔗  СПОЧАТКУ ПРИВʼЯЗКА')
            ."Щоб подати заявку, привʼяжіть цей чат до акаунту\nна сайті — це один дотик.\n\n"
            ."<b>Як:</b>\n"
            ."  1. Зареєструйтесь на сайті\n"
            ."  2. Профіль → Telegram → «Привʼязати Telegram»\n"
            ."  3. Сайт сам відкриє цей чат\n\n"
            ."Після цього кнопка «Подати заявку» запрацює.\n\n"
            .self::RULE;

        $rows = [];
        if ($site = $this->siteUrl()) {
            $rows[] = [['text' => '🌐  Відкрити профіль на сайті', 'url' => rtrim($site, '/').'/profile']];
        }
        $rows[] = [$this->backHome()];

        return $this->screen($text, $rows);
    }

    /** Заявку схвалено — лишилось увійти до групи. */
    public function approved(TelegramApplication $application, ?string $inviteLink): array
    {
        $text = $this->head('✅  ВАС ПРИЙНЯТО')
            ."Вітаємо в Monsory Family, <b>".e($application->nickname)."</b>.\n\n";

        if ($application->joined_at) {
            $text .= "Ви вже у групі родини.\n\n".self::RULE;

            return $this->screen($text, [[$this->backHome()]]);
        }

        if ($inviteLink === null) {
            $text .= "Посилання на групу зараз недоступне —\nзверніться до керівництва.\n\n".self::RULE;

            return $this->screen($text, [[$this->backHome()]]);
        }

        $text .= "Лишився один крок — увійдіть до групи родини.\n\n"
            ."<i>Посилання персональне й одноразове.</i>\n\n"
            .self::RULE;

        return $this->screen($text, [
            [['text' => '🚪  Увійти до групи', 'url' => $inviteLink]],
            [$this->backHome()],
        ]);
    }

    public function applyIntro(?TelegramApplication $pending): array
    {
        if ($pending) {
            $text = $this->head('📝  ЗАЯВКА')
                ."Ваша заявка вже на розгляді.\n\n"
                .'<b>Нік:</b> '.e($pending->nickname)."\n"
                .'<b>Подано:</b> '.$pending->created_at->format('d.m.Y H:i')."\n\n"
                ."Ми розглядаємо кожну особисто, а не масово,\n"
                ."тому відповідь приходить не миттєво —\n"
                ."але приходить сюди ж, у цей чат.\n\n"
                .self::RULE;

            return $this->screen($text, [
                [$this->btn('🗑  Скасувати заявку', 'apply:cancel')],
                [$this->backHome()],
            ]);
        }

        $text = $this->head('📝  ЗАЯВКА ДО РОДИНИ')
            ."Шість коротких питань — більшість із них\nу кнопках, вручну треба лише нік.\n\n"
            ."Заявку читає людина, а не фільтр.\n\n"
            .self::RULE;

        return $this->screen($text, [
            [$this->btn('▶️  Почати', 'apply:start')],
            [$this->backHome()],
        ]);
    }

    public function askNickname(): array
    {
        return $this->screen(
            $this->step(1)."<b>Ваш нік у грі</b>\n\nНадішліть його повідомленням у цей чат.",
            [[$this->btn('✖️  Скасувати', 'apply:cancel')]]
        );
    }

    public function askAge(): array
    {
        return $this->screen(
            $this->step(2).'<b>Скільки вам років?</b>',
            $this->options(self::AGES, 'apply:age', 2)
        );
    }

    public function askPlaytime(): array
    {
        return $this->screen(
            $this->step(3)."<b>Скільки часу приділяєте грі?</b>\n\n<i>Відповідайте чесно — від цього залежить,\nчи зійдемось ми за темпом.</i>",
            $this->options(self::PLAYTIME, 'apply:time', 1)
        );
    }

    public function askExperience(): array
    {
        return $this->screen(
            $this->step(4).'<b>Ваш досвід у RP?</b>',
            $this->options(self::EXPERIENCE, 'apply:exp', 2)
        );
    }

    public function askDirection(): array
    {
        return $this->screen(
            $this->step(5)."<b>Який напрямок вам ближчий?</b>\n\n<i>Не остаточний вибір — просто орієнтир.</i>",
            $this->options(self::DIRECTIONS, 'apply:dir', 1)
        );
    }

    public function askAbout(): array
    {
        return $this->screen(
            $this->step(6)."<b>Пару слів про себе</b>\n\nНадішліть повідомленням — або пропустіть.",
            [
                [$this->btn('⏭  Пропустити', 'apply:skip')],
                [$this->btn('✖️  Скасувати', 'apply:cancel')],
            ]
        );
    }

    /** @param array<string,mixed> $draft */
    public function confirm(array $draft): array
    {
        $text = $this->head('📋  ПЕРЕВІРТЕ ЗАЯВКУ')
            .'<b>Нік:</b> '.e((string) ($draft['nickname'] ?? '—'))."\n"
            .'<b>Вік:</b> '.e((string) ($draft['age_range'] ?? '—'))."\n"
            .'<b>Час у грі:</b> '.e((string) ($draft['playtime'] ?? '—'))."\n"
            .'<b>Досвід:</b> '.e((string) ($draft['experience'] ?? '—'))."\n"
            .'<b>Напрямок:</b> '.e((string) ($draft['direction'] ?? '—'))."\n";

        $about = trim((string) ($draft['about'] ?? ''));
        $text .= '<b>Про себе:</b> '.($about === '' ? '<i>не вказано</i>' : e($about))."\n\n".self::RULE;

        return $this->screen($text, [
            [$this->btn('✅  Надіслати', 'apply:send')],
            [$this->btn('🔄  Заповнити заново', 'apply:start')],
            [$this->btn('✖️  Скасувати', 'apply:cancel')],
        ]);
    }

    public function submitted(): array
    {
        $text = $this->head('✅  ЗАЯВКУ НАДІСЛАНО')
            ."Дякуємо. Ми розглядаємо кожну заявку особисто,\nтому відповідь приходить не миттєво.\n\n"
            ."Рішення прийде сюди ж, у цей чат.\n\n"
            .self::RULE;

        return $this->screen($text, [[$this->backHome()]]);
    }

    public function cancelled(): array
    {
        return $this->screen(
            $this->head('ЗАЯВКУ СКАСОВАНО')."Ви можете подати нову будь-коли.\n\n".self::RULE,
            [[$this->btn('📝  Подати заявку', 'apply')], [$this->backHome()]]
        );
    }

    /**
     * Итог решения, принятого кнопкой прямо в чате.
     *
     * Карточку заявки заменяем итогом, а не оставляем кнопки: иначе
     * второй администратор видит «Схвалити» у уже разобранной заявки и
     * жмёт впустую.
     */
    public function reviewResult(string $message, ?TelegramApplication $application): array
    {
        $text = $this->head('РІШЕННЯ ЗА ЗАЯВКОЮ');

        if ($application) {
            $status = match ($application->status) {
                TelegramApplication::STATUS_APPROVED => '✅  схвалено',
                TelegramApplication::STATUS_REJECTED => '✖️  відхилено',
                default => 'на розгляді',
            };

            $text .= '<b>'.e($application->nickname)."</b>\n".$status."\n";

            if ($application->reviewer) {
                $text .= '<i>'.e($application->reviewer->name)."</i>\n";
            }

            $text .= "\n";
        }

        return $this->screen($text.e($message)."\n\n".self::RULE, [[$this->backHome()]]);
    }

    /* ==================== СЛУЖЕБНОЕ ==================== */

    /** Экран, когда бот не настроен или что-то пошло не так. */
    public function oops(): array
    {
        return $this->screen(
            $this->head('ЩОСЬ ПІШЛО НЕ ТАК')."Спробуйте ще раз або поверніться в меню.\n\n".self::RULE,
            [[$this->backHome()]]
        );
    }

    private function head(string $title): string
    {
        return '◆  <b>'.$title."</b>\n".self::RULE."\n\n";
    }

    private function step(int $n): string
    {
        // Прогресс шагами: без него анкета из шести вопросов ощущается
        // бесконечной, и люди бросают её на середине.
        $total = 6;
        $bar = str_repeat('▰', $n).str_repeat('▱', $total - $n);

        return '◆  <b>ЗАЯВКА</b>   '.$bar."   {$n}/{$total}\n".self::RULE."\n\n";
    }

    /**
     * @param  array<int,string>  $labels
     * @return array<int,array<int,array<string,string>>>
     */
    private function options(array $labels, string $prefix, int $perRow): array
    {
        $rows = [];
        $buffer = [];
        foreach ($labels as $i => $label) {
            $buffer[] = $this->btn($label, $prefix.':'.$i);
            if (count($buffer) === $perRow) {
                $rows[] = $buffer;
                $buffer = [];
            }
        }
        if ($buffer) {
            $rows[] = $buffer;
        }

        $rows[] = [$this->btn('✖️  Скасувати', 'apply:cancel')];

        return $rows;
    }

    /** @return array{text:string,keyboard:array<string,mixed>} */
    private function screen(string $text, array $rows): array
    {
        return ['text' => $text, 'keyboard' => ['inline_keyboard' => $rows]];
    }

    /** @return array<string,string> */
    private function btn(string $label, string $data): array
    {
        return ['text' => $label, 'callback_data' => $data];
    }

    /** @return array<string,string> */
    private function backHome(): array
    {
        return $this->btn('🏠  Головне меню', 'home');
    }

    private function siteUrl(): ?string
    {
        $url = trim((string) config('app.url'));

        return $url !== '' && str_starts_with($url, 'http') ? $url : null;
    }
}
