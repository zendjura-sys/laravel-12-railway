<?php

namespace Addons\Bonuses\Support;

use Addons\Bonuses\Models\ManualBonusAward;
use Addons\Messenger\Services\SystemInbox;
use Addons\Notifications\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Єдиний голос банку — "Monsory Finance". Кожна фінансова подія йде
 * двома каналами: особисте сповіщення (дзвіночок + push + Telegram, через
 * Notifications) і запис у службовий чат Monsory Finance (Messenger), куди
 * веде кнопка сповіщення. Обидва модулі опційні (class_exists) — без
 * месенджера лишається саме сповіщення, без Notifications — лише чат.
 *
 * receipt() — квитанція про власну дію учасника (переказ, депозит, запит
 * готівки): лише в чат, без push, бо про свою дію людина й так знає.
 */
final class FinanceNotifier
{
    public const SENDER = 'Monsory Finance';

    public static function notify(?User $user, string $type, string $emoji, string $title, string $text): void
    {
        if (! $user) {
            return;
        }

        $chatUrl = self::postToChat($user, $emoji, $title, $text);

        if (! class_exists(NotificationService::class)) {
            return;
        }

        $button = match (true) {
            $chatUrl !== null => ['text' => '🏦  '.self::SENDER, 'url' => $chatUrl],
            Route::has('bonuses.index') => ['text' => '🏦  Відкрити банк', 'url' => route('bonuses.index')],
            default => null,
        };

        app(NotificationService::class)->notify($user, $type, self::SENDER.' · '.$title, $text, $button);
    }

    public static function receipt(?User $user, string $emoji, string $title, string $text): void
    {
        if ($user) {
            self::postToChat($user, $emoji, $title, $text);
        }
    }

    /** Спільне для веб- і мобільної адмінки — щоб тексти не розходились. */
    public static function manualAwardGranted(ManualBonusAward $award, User $admin): void
    {
        // Свіжий User, а не $award->user: зв'язок міг бути підвантажений з
        // обрізаним набором колонок (user:id,name), а сповіщенню потрібні
        // telegram_chat_id та інші поля.
        self::notify(
            User::find($award->user_id),
            'bank_manual_award',
            '🎁',
            'Нараховано премію',
            '+'.self::money($award->amount)." на ваш рахунок.\nНарахував(-ла): {$admin->name}".($award->note ? "\nКоментар: «{$award->note}»" : ''),
        );
    }

    public static function manualAwardRevoked(ManualBonusAward $award, User $admin): void
    {
        self::notify(
            User::find($award->user_id),
            'bank_manual_award_revoked',
            '↩️',
            'Премію анульовано',
            '−'.self::money($award->amount)." — ручне нарахування анульовано.\nАнулював(-ла): {$admin->name}",
        );
    }

    public static function money(int|float $amount): string
    {
        return number_format($amount, 0, ',', ' ').'₴';
    }

    /** @return string|null Посилання на чат Monsory Finance, якщо запис вдався. */
    private static function postToChat(User $user, string $emoji, string $title, string $text): ?string
    {
        if (! class_exists(SystemInbox::class)) {
            return null;
        }

        // Гроші на цей момент уже рухнули — збій опційного чату не повинен
        // зірвати саму операцію чи сповіщення про неї.
        try {
            $conversation = app(SystemInbox::class)->postFinance($user, "{$emoji} {$title}\n{$text}");
        } catch (Throwable $e) {
            Log::warning('finance_notifier.chat_failed', ['user_id' => $user->id, 'message' => $e->getMessage()]);

            return null;
        }

        return Route::has('messenger.show') ? route('messenger.show', $conversation->id) : null;
    }
}
