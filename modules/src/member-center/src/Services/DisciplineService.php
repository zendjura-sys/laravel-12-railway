<?php

namespace Addons\MemberCenter\Services;

use Addons\MemberCenter\Events\MemberPenaltyUpdated;
use Addons\MemberCenter\Events\MemberReprimandLimitReached;
use Addons\MemberCenter\Events\MemberWarningIssued;
use Addons\MemberCenter\Models\MemberWarning;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

/**
 * Система покарань за правилами родини Monsory (розділи 5.6 і 8):
 *  - 3 активні зауваження → автоматична догана (п. 8.2);
 *  - нове порушення продовжує строк дії активних зауважень (7 дн.) і доган
 *    (14 дн.) — «згорає, якщо за цей час не було нових порушень» (п. 8.3);
 *  - штраф сплачується 48 год; прострочений — подвоюється й дає догану (п. 5.6);
 *  - 3/3 догани — сповіщення керівництву про виключення (п. 8.6).
 *
 * Модуль не залежить від Notifications/Bonuses напряму: сповіщення — через
 * події, оплата з банку — через class_exists(BalanceCalculator).
 */
class DisciplineService
{
    public const REMARKS_PER_REPRIMAND = 3;

    public const REPRIMAND_LIMIT = 3;

    public function issue(
        User $user,
        ?User $author,
        string $type,
        string $reason,
        ?string $ruleCode = null,
        ?int $amount = null,
        bool $auto = false,
        ?int $sourceId = null,
    ): MemberWarning {
        if (! in_array($type, MemberWarning::TYPES, true)) {
            throw ValidationException::withMessages(['type' => 'Невідомий тип покарання.']);
        }
        if ($type === 'fine' && (! $amount || $amount < 1)) {
            throw ValidationException::withMessages(['amount' => 'Для штрафу вкажіть суму.']);
        }

        return DB::transaction(function () use ($user, $author, $type, $reason, $ruleCode, $amount, $auto, $sourceId) {
            // Нове порушення (не автоматична ескалація) — лічильник "без
            // порушень" починається спочатку для всіх активних покарань.
            if (! $auto) {
                $this->restartTimers($user);
            }

            $days = MemberWarning::LIFETIME_DAYS[$type] ?? null;

            $warning = MemberWarning::create([
                'user_id' => $user->id,
                // author_id NOT NULL у схемі: автоматичні рішення записуються
                // на того, хто видав покарання-джерело, або на самого учасника.
                'author_id' => $author?->id ?? $user->id,
                'type' => $type,
                'severity' => $type === 'reprimand' ? 'severe' : 'notice',
                'rule_code' => $ruleCode,
                'reason' => $reason,
                'amount' => $type === 'fine' ? $amount : null,
                'status' => 'active',
                'due_at' => $type === 'fine' ? now()->addHours(MemberWarning::FINE_DUE_HOURS) : null,
                'expires_at' => $days ? now()->addDays($days) : null,
                'auto' => $auto,
                'source_id' => $sourceId,
            ]);

            DB::afterCommit(fn () => Event::dispatch(new MemberWarningIssued($warning)));

            if ($type === 'remark') {
                $this->convertRemarksIfNeeded($user, $author);
            }
            if ($type === 'reprimand') {
                $this->checkReprimandLimit($user);
            }

            return $warning;
        });
    }

    public function revoke(MemberWarning $warning, User $by, string $note): MemberWarning
    {
        if (in_array($warning->status, ['revoked', 'expired', 'converted', 'paid'], true)) {
            throw ValidationException::withMessages(['warning' => 'Це покарання вже не діє.']);
        }

        $warning->update([
            'status' => 'revoked',
            'resolved_by' => $by->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ]);
        Event::dispatch(new MemberPenaltyUpdated($warning, 'revoked'));

        return $warning;
    }

    /** Керівництво підтверджує оплату поза банком (готівкою в грі тощо). */
    public function markPaid(MemberWarning $warning, User $by): MemberWarning
    {
        if (! $warning->isUnpaidFine()) {
            throw ValidationException::withMessages(['warning' => 'Цей штраф уже не очікує оплати.']);
        }

        $warning->update([
            'status' => 'paid',
            'paid_at' => now(),
            'paid_via' => 'manual',
            'resolved_by' => $by->id,
            'resolved_at' => now(),
        ]);
        Event::dispatch(new MemberPenaltyUpdated($warning, 'paid'));

        return $warning;
    }

    public static function bankAvailable(): bool
    {
        return class_exists(\Addons\Bonuses\Services\BalanceCalculator::class);
    }

    /** Учасник сплачує штраф зі свого рахунку в банку родини. */
    public function payFromBank(MemberWarning $warning, User $user): MemberWarning
    {
        if ($warning->user_id !== $user->id) {
            abort(403);
        }
        if (! self::bankAvailable()) {
            throw ValidationException::withMessages(['warning' => 'Банк родини зараз недоступний — сплатіть штраф керівництву.']);
        }

        DB::transaction(function () use ($warning, $user) {
            $fresh = MemberWarning::query()->lockForUpdate()->findOrFail($warning->id);
            if (! $fresh->isUnpaidFine()) {
                throw ValidationException::withMessages(['warning' => 'Цей штраф уже не очікує оплати.']);
            }
            // Баланс звіряємо ВСЕРЕДИНІ транзакції — поруч міг пройти переказ.
            $balance = \Addons\Bonuses\Services\BalanceCalculator::balanceFor($user->id);
            if ($fresh->amount > $balance) {
                throw ValidationException::withMessages(['warning' => 'Недостатньо коштів на рахунку. Баланс: '.number_format($balance, 0, ',', ' ').'₴.']);
            }

            $fresh->update(['status' => 'paid', 'paid_at' => now(), 'paid_via' => 'bank']);
            $warning->setRawAttributes($fresh->getAttributes(), true);
        });

        Event::dispatch(new MemberPenaltyUpdated($warning, 'paid'));

        return $warning;
    }

    /** Планувальник: прострочені штрафи (подвоєння + догана) і згорілі покарання. */
    public function processDeadlines(): array
    {
        $overdue = 0;
        $expired = 0;

        MemberWarning::query()
            ->where('type', 'fine')->where('status', 'active')
            ->where('due_at', '<', now())
            ->get()
            ->each(function (MemberWarning $fine) use (&$overdue) {
                DB::transaction(function () use ($fine) {
                    $fine->update([
                        'status' => 'overdue',
                        'original_amount' => $fine->amount,
                        'amount' => $fine->amount * 2,
                    ]);
                    $user = User::find($fine->user_id);
                    if ($user) {
                        $this->issue(
                            $user,
                            User::find($fine->author_id),
                            'reprimand',
                            'Штраф '.self::money($fine->original_amount).' не сплачено вчасно — сума подвоєна до '.self::money($fine->amount).'.',
                            '5.6',
                            auto: true,
                            sourceId: $fine->id,
                        );
                    }
                });
                Event::dispatch(new MemberPenaltyUpdated($fine, 'overdue'));
                $overdue++;
            });

        MemberWarning::query()
            ->whereIn('type', array_keys(MemberWarning::LIFETIME_DAYS))
            ->where('status', 'active')
            ->where('expires_at', '<', now())
            ->get()
            ->each(function (MemberWarning $w) use (&$expired) {
                $w->update(['status' => 'expired', 'resolved_at' => now()]);
                Event::dispatch(new MemberPenaltyUpdated($w, 'expired'));
                $expired++;
            });

        return ['overdue' => $overdue, 'expired' => $expired];
    }

    /** Поточний стан учасника — для його сторінки й бейджів. */
    public function summary(User $user): array
    {
        $active = MemberWarning::query()->where('user_id', $user->id)->whereIn('status', ['active', 'overdue'])->get();

        return [
            'remarks' => $active->where('type', 'remark')->where('status', 'active')->count(),
            'remarksLimit' => self::REMARKS_PER_REPRIMAND,
            'reprimands' => $active->where('type', 'reprimand')->where('status', 'active')->count(),
            'reprimandsLimit' => self::REPRIMAND_LIMIT,
            'unpaidFines' => $active->where('type', 'fine')->count(),
            'unpaidFinesAmount' => (int) $active->where('type', 'fine')->sum('amount'),
            'bonusHalved' => $active->where('type', 'reprimand')->where('status', 'active')->isNotEmpty(),
        ];
    }

    public static function hasActiveReprimand(int $userId): bool
    {
        return MemberWarning::query()
            ->where('user_id', $userId)->where('type', 'reprimand')->where('status', 'active')
            ->exists();
    }

    public static function money(?int $amount): string
    {
        return number_format((int) $amount, 0, ',', ' ').'₴';
    }

    private function restartTimers(User $user): void
    {
        foreach (MemberWarning::LIFETIME_DAYS as $type => $days) {
            MemberWarning::query()
                ->where('user_id', $user->id)->where('type', $type)->where('status', 'active')
                ->update(['expires_at' => now()->addDays($days)]);
        }
    }

    private function convertRemarksIfNeeded(User $user, ?User $author): void
    {
        $remarks = MemberWarning::query()
            ->where('user_id', $user->id)->where('type', 'remark')->where('status', 'active')
            ->oldest()
            ->limit(self::REMARKS_PER_REPRIMAND)
            ->get();

        if ($remarks->count() < self::REMARKS_PER_REPRIMAND) {
            return;
        }

        MemberWarning::query()->whereIn('id', $remarks->pluck('id'))
            ->update(['status' => 'converted', 'resolved_at' => now()]);

        $this->issue(
            $user,
            $author,
            'reprimand',
            'Автоматично: '.self::REMARKS_PER_REPRIMAND.' активні зауваження перетворено на догану.',
            '8.2',
            auto: true,
            sourceId: $remarks->last()->id,
        );
    }

    private function checkReprimandLimit(User $user): void
    {
        $count = MemberWarning::query()
            ->where('user_id', $user->id)->where('type', 'reprimand')->where('status', 'active')
            ->count();

        if ($count >= self::REPRIMAND_LIMIT) {
            DB::afterCommit(fn () => Event::dispatch(new MemberReprimandLimitReached($user, $count)));
        }
    }
}
