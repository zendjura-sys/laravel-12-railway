<?php

use Addons\FamilyEvents\Events\FamilyEventCreated;
use Addons\FamilyEvents\Events\FamilyEventReminder;
use Addons\FamilyEvents\Events\FamilyEventStartingSoon;
use Addons\FamilyGoals\Events\FamilyGoalCompleted;
use Addons\MemberCenter\Events\LeaveRequestReviewed;
use Addons\Notifications\Listeners\NotifyOnAchievementUnlocked;
use Addons\Notifications\Listeners\NotifyOnFamilyEventCreated;
use Addons\Notifications\Listeners\NotifyOnFamilyEventReminder;
use Addons\Notifications\Listeners\NotifyOnFamilyEventStartingSoon;
use Addons\Notifications\Listeners\NotifyOnFamilyGoalCompleted;
use Addons\Notifications\Listeners\NotifyOnLeaveRequestReviewed;
use Addons\Notifications\Listeners\NotifyOnReportReviewed;
use Addons\Notifications\Listeners\NotifyReviewersOnReportCreated;
use Addons\Progression\Events\AchievementUnlocked;
use Addons\Reports\Events\ReportCreated;
use Addons\Reports\Events\ReportReviewed;
use Illuminate\Support\Facades\Event;

// Кожен клас події тут — рядковий літерал на етапі компіляції (::class не
// тригерить автозавантаження), тож реєстрація безпечна навіть якщо
// відповідний модуль-джерело не встановлено чи не активовано: подія
// просто ніколи не диспатчиться, і listener ніколи не викличеться.
Event::listen(ReportReviewed::class, NotifyOnReportReviewed::class);
Event::listen(ReportCreated::class, NotifyReviewersOnReportCreated::class);
Event::listen(AchievementUnlocked::class, NotifyOnAchievementUnlocked::class);
Event::listen(LeaveRequestReviewed::class, NotifyOnLeaveRequestReviewed::class);
Event::listen(FamilyGoalCompleted::class, NotifyOnFamilyGoalCompleted::class);
Event::listen(FamilyEventCreated::class, NotifyOnFamilyEventCreated::class);
Event::listen(FamilyEventReminder::class, NotifyOnFamilyEventReminder::class);
Event::listen(FamilyEventStartingSoon::class, NotifyOnFamilyEventStartingSoon::class);
