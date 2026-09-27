<?php

namespace Addons\Progression\Listeners;

use Addons\Progression\Services\ProgressionService;
use Addons\TelegramBot\Events\AccountLinked;
use App\Models\User;

class UnlockAchievementOnAccountLinked
{
    public function __construct(private readonly ProgressionService $service)
    {
    }

    public function handle(AccountLinked $event): void
    {
        $user = User::find($event->userId);
        if ($user) {
            $this->service->handleAccountLinked($user);
        }
    }
}
