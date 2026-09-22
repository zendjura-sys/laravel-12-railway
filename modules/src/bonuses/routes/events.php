<?php

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Services\BonusCalculator;
use Addons\Bonuses\Services\BonusDigest;
use Addons\Notifications\Services\NotificationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;

// Файл підключається на кожен boot (включно з artisan schedule:run), тому
// реєстрація тут безпечна — той самий приём, що й у Progression.
// Субота 23:00 Europe/Kyiv — рахує щойно завершений тиждень, одразу
// зараховує премії на рахунки учасників (банк) і шле дайджест у сімейний чат.
app(Schedule::class)
    ->call(function () {
        $calculator = app(BonusCalculator::class);
        // Рівно о 23:00 currentWeekStart() — це вже початок НОВОГО тижня
        // (ще порожнього), тож тиждень, що щойно завершився, — на один назад.
        $weekStart = $calculator->currentWeekStart()->subWeek();

        $credited = $calculator->runWeeklyPayouts($weekStart, credit: true);
        app(BonusDigest::class)->sendFor($weekStart);

        if (! class_exists(NotificationService::class)) {
            return;
        }
        $button = Route::has('bonuses.index')
            ? ['text' => '🏦  Відкрити банк', 'url' => route('bonuses.index')]
            : null;
        $credited->each(fn (BonusPayout $payout) => app(NotificationService::class)->notify(
            $payout->user,
            'bonus_credited',
            'Премію зараховано',
            'На ваш рахунок зараховано '.number_format($payout->total_amount, 0, ',', ' ')
                .'₴ — премія за тиждень від '.$weekStart->format('d.m.Y').'.',
            $button,
        ));
    })
    ->name('bonuses-weekly-payouts')
    ->weeklyOn(6, '23:00')
    ->timezone('Europe/Kyiv')
    ->withoutOverlapping();
