<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;

class NotifyOnAchievementUnlocked
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $user = User::find($event->userId);
        if (! $user) {
            return;
        }

        $this->notifications->notify(
            $user,
            'achievement_unlocked',
            'Нова ачівка!',
            "Ви розблокували «{$event->achievementName}».",
        );
    }
}
