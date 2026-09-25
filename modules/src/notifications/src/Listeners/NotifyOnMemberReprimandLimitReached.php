<?php

namespace Addons\Notifications\Listeners;

use Addons\Notifications\Services\NotificationService;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/** 3/3 догани — керівництво (members.manage) має вирішити питання виключення (п. 8.6). */
class NotifyOnMemberReprimandLimitReached
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $user = $event->user;
        $button = Route::has('admin.discipline.index')
            ? ['text' => '⚖️  Покарання', 'url' => route('admin.discipline.index', ['user' => $user->id, 'status' => 'all'])]
            : null;

        User::permission('members.manage')->get()->each(fn (User $lead) => $this->notifications->notify(
            $lead,
            'member_reprimand_limit',
            "{$user->name}: {$event->count}/3 догани",
            'Учасник набрав максимум доган — за п. 8.6 правил родини підлягає виключенню. Протягом 24 годин він може оскаржити останню догану.',
            $button,
        ));
    }
}
