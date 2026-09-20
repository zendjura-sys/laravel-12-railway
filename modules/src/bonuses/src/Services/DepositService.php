<?php

namespace Addons\Bonuses\Services;

use Addons\Bonuses\Models\BankDeposit;
use Addons\Bonuses\Models\BonusSettings;
use Addons\Notifications\Services\NotificationService;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Депозит "заморожує" частину балансу на строк — ставка й строк
 * фіксуються в самому рядку в момент відкриття (зі settings того часу),
 * тому пізніша зміна ставки в адмінці не чіпає вже відкриті депозити.
 *
 * Дозрівання — ліниве: жодного scheduled job немає, замість цього
 * settleMaturedFor() викликається на кожен вхід учасника на сторінку
 * банку чи дію з балансом (переказ/новий депозит) і закриває все, що
 * вже дозріло на цей момент — той самий підхід, що й "порахований на
 * льоту, не збережений" баланс у BalanceCalculator.
 */
class DepositService
{
    public static function open(User $user, int $amount): BankDeposit
    {
        $settings = BonusSettings::current();

        if (! $settings->deposit_enabled) {
            throw ValidationException::withMessages(['amount' => 'Депозити тимчасово вимкнено.']);
        }

        if ($amount < $settings->deposit_min_amount) {
            throw ValidationException::withMessages(['amount' => "Мінімальна сума депозиту — {$settings->deposit_min_amount}₴."]);
        }

        $balance = BalanceCalculator::balanceFor($user->id);
        if ($amount > $balance) {
            throw ValidationException::withMessages(['amount' => 'Недостатньо коштів на балансі.']);
        }

        return BankDeposit::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'interest_rate' => $settings->deposit_interest_rate,
            'term_days' => $settings->deposit_term_days,
            'matures_at' => now()->addDays($settings->deposit_term_days),
            'status' => 'active',
        ]);
    }

    /** Закриває всі дозрілі депозити користувача — з відсотком, як і було обіцяно при відкритті. */
    public static function settleMaturedFor(int $userId): void
    {
        $matured = BankDeposit::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->where('matures_at', '<=', now())
            ->get();

        foreach ($matured as $deposit) {
            $deposit->update([
                'status' => 'completed',
                'payout_amount' => $deposit->projectedPayout(),
                'closed_at' => now(),
            ]);

            if (class_exists(NotificationService::class)) {
                app(NotificationService::class)->notify(
                    $deposit->user,
                    'deposit_matured',
                    'Депозит дозрів',
                    "Ваш депозит на {$deposit->amount}₴ дозрів — повернено {$deposit->payout_amount}₴ на баланс.",
                );
            }
        }
    }

    /** Дострокове зняття — без відсотка, лише сума вкладу (втрата відсотка — і є "штраф"). */
    public static function withdrawEarly(BankDeposit $deposit): void
    {
        if ($deposit->status !== 'active') {
            throw ValidationException::withMessages(['deposit' => 'Цей депозит уже закрито.']);
        }

        $deposit->update([
            'status' => 'withdrawn',
            'payout_amount' => $deposit->amount,
            'closed_at' => now(),
        ]);
    }
}
