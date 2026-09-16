<?php

use Addons\Notifications\Listeners\NotifyOnReportReviewed;
use Addons\Reports\Events\ReportReviewed;
use Illuminate\Support\Facades\Event;

// Addons\Reports\Events\ReportReviewed — рядковий літерал на етапі
// компіляції (::class не тригерить автозавантаження), тож безпечно навіть
// якщо Reports не встановлено: подія просто ніколи не диспатчиться.
Event::listen(ReportReviewed::class, NotifyOnReportReviewed::class);
