<?php

use Addons\TelegramBot\Services\BirthdayGreeter;
use Illuminate\Console\Scheduling\Schedule;

// Файл підключається на кожен boot (включно з artisan schedule:run), тому
// реєстрація тут безпечна — той самий приём, що й у Bonuses/Progression.
// 10:00 Europe/Kyiv, раз на добу: перевіряє, у кого сьогодні birth_date
// (місяць+день, рік не має значення), і вітає одним повідомленням у
// сімейний Telegram-чат.
app(Schedule::class)
    ->call(fn () => app(BirthdayGreeter::class)->greetToday())
    ->name('telegram-birthday-greetings')
    ->dailyAt('10:00')
    ->timezone('Europe/Kyiv')
    ->withoutOverlapping();
