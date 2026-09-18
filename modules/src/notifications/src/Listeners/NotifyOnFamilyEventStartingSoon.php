<?php

namespace Addons\Notifications\Listeners;

use Addons\TelegramBot\Services\FamilyGroup;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Нагадування "за 15 хвилин до початку" — друге, окреме від денного
 * (NotifyOnFamilyEventReminder), той самий fan-out + груп-чат приём.
 */
class NotifyOnFamilyEventStartingSoon
{
    public function handle($event): void
    {
        $familyEvent = $event->event;
        $when = $familyEvent->starts_at->locale('uk')->isoFormat('HH:mm');

        $title = 'Скоро починається: '.$familyEvent->title;
        $body = 'Початок о '.$when.' (через 15 хв)'.($familyEvent->location ? ' · '.$familyEvent->location : '');

        $now = now();
        User::query()->select('id')->chunk(200, function ($users) use ($title, $body, $now) {
            DB::table('notifications')->insert($users->map(fn (User $user) => [
                'user_id' => $user->id,
                'type' => 'family_event_starting_soon',
                'title' => $title,
                'body' => $body,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        if (class_exists(FamilyGroup::class) && class_exists(TelegramClient::class)) {
            $client = new TelegramClient();
            $familyGroup = new FamilyGroup($client);
            if ($familyGroup->isConfigured()) {
                $client->sendMessage($familyGroup->id(), '<b>⏳ '.e($title).'</b>'."\n\n".e($body));
            }
        }
    }
}
