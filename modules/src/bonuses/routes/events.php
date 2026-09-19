<?php

use Addons\Bonuses\Services\BonusCalculator;
use Addons\Bonuses\Services\BonusDigest;
use Illuminate\Console\Scheduling\Schedule;

// Файл підключається на кожен boot (включно з artisan schedule:run), тому
// реєстрація тут безпечна — той самий приём, що й у Progression.
// Субота 23:00 Europe/Kyiv — рахує щойно завершений тиждень і одразу шле
// дайджест у сімейний чат, поки в BonusCalculator::currentWeekStart() і
// digest-запиті використовується той самий тижневий якір.
app(Schedule::class)
    ->call(function () {
        $calculator = app(BonusCalculator::class);
        $weekStart = $calculator->currentWeekStart();
        $calculator->runWeeklyPayouts();
        app(BonusDigest::class)->sendFor($weekStart);
    })
    ->name('bonuses-weekly-payouts')
    ->weeklyOn(6, '23:00')
    ->timezone('Europe/Kyiv')
    ->withoutOverlapping();
