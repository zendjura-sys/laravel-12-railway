<?php

namespace Addons\Bonuses\Services;

use Addons\Bonuses\Models\BankDeposit;
use Addons\Bonuses\Models\BankTransfer;
use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\ManualBonusAward;

/**
 * Баланс картки — не сума всіх нарахувань "за все життя", а РЕАЛЬНО
 * доступні кошти: виплачені (paid=true) автоматичні премії + ручні
 * премії (ті завжди "реальні", без окремого статусу виплати), мінус усе
 * відправлене переказами, плюс усе отримане.
 *
 * Порахований на льоту кількома SUM-запитами, а не збережений стовпцем:
 * той самий підхід, що й cumulativeInvestment у BonusController — для
 * масштабу цього сайту кеш-стовпець, який може розсинхронізуватись,
 * коштує дорожче, ніж кілька агрегатних запитів на відкриття сторінки.
 */
class BalanceCalculator
{
    public static function balanceFor(int $userId): int
    {
        $earned = (int) BonusPayout::query()->where('user_id', $userId)->where('paid', true)->sum('total_amount')
            + (int) ManualBonusAward::query()->where('user_id', $userId)->sum('amount');

        $sent = (int) BankTransfer::query()->where('from_user_id', $userId)->whereNull('reversed_at')->sum('amount');
        $received = (int) BankTransfer::query()->where('to_user_id', $userId)->whereNull('reversed_at')->sum('amount');

        // Активні депозити заморожені (віднімаються), закриті — повертають
        // principal (+ відсоток, якщо дозріли природно) назад у баланс.
        $locked = (int) BankDeposit::query()->where('user_id', $userId)->where('status', 'active')->sum('amount');
        $returned = (int) BankDeposit::query()->where('user_id', $userId)->whereIn('status', ['completed', 'withdrawn'])->sum('payout_amount');

        return $earned - $sent + $received - $locked + $returned;
    }
}
