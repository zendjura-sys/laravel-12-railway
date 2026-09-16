<?php

use Addons\FamilyGoals\Listeners\LogReportActivity;
use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Events\ReportReviewed;
use Illuminate\Support\Facades\Event;

// Addons\Reports\Events\* — це лише рядкові літерали на етапі компіляції
// (::class не тригерить автозавантаження), тож реєстрація безпечна навіть
// якщо Reports не встановлено чи не активовано: подія просто ніколи не
// диспатчиться, і ці listener'и ніколи не викличуться — без помилок.
Event::listen(ReportCreated::class, [LogReportActivity::class, 'handleCreated']);
Event::listen(ReportReviewed::class, [LogReportActivity::class, 'handleReviewed']);
