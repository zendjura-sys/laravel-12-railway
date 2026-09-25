<?php

use Addons\AiAssistant\Listeners\DispatchReportPhotoAnalysis;
use Addons\Reports\Events\ReportCreated;
use Illuminate\Support\Facades\Event;

// Той самий приём, що й скрізь у проєкті: клас події — рядковий літерал на
// етапі компіляції, реєстрація безпечна навіть якщо Reports не встановлено
// чи не активовано — подія просто ніколи не диспатчиться.
Event::listen(ReportCreated::class, DispatchReportPhotoAnalysis::class);
