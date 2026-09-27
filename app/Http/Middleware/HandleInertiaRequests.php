<?php

namespace App\Http\Middleware;

use App\Support\DesignSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            // Оформление задаётся в админке (Дизайн), а не собирается в
            // бандл, поэтому уезжает на фронт с каждым ответом.
            'design' => DesignSettings::all(),
            // Публічний VAPID-ключ потрібен клієнту для pushManager.subscribe();
            // приватний ключ на фронт ніколи не потрапляє. null, поки не
            // налаштовано в .env — форма підписки просто себе ховає.
            'webPushPublicKey' => config('webpush.vapid.public_key'),
            // Пункт «Союз» у навігації адмінки ховається, поки фіча вимкнена
            // (UNION_DOMAIN порожній) — так само, як сам union.monsory.net.
            'unionEnabled' => (bool) config('app.union_domain'),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            'can' => [
                'manageAddons' => $request->user()?->can('addons.manage') ?? false,
                'manageReports' => $request->user()?->can('reports.manage') ?? false,
                'manageProgression' => $request->user()?->can('progression.manage') ?? false,
                'manageSettings' => $request->user()?->can('settings.manage') ?? false,
                'manageRoles' => $request->user()?->can('roles.manage') ?? false,
                'manageUsers' => $request->user()?->can('users.manage') ?? false,
                'manageMembers' => $request->user()?->can('members.manage') ?? false,
                'manageGoals' => $request->user()?->can('goals.manage') ?? false,
                'manageBroadcasts' => $request->user()?->can('broadcasts.manage') ?? false,
                'manageTelegram' => $request->user()?->can('telegram.manage') ?? false,
                'manageBonuses' => $request->user()?->can('bonuses.manage') ?? false,
                'manageEvents' => $request->user()?->can('events.manage') ?? false,
                'manageUnion' => $request->user()?->can('union.manage') ?? false,
            ],
            // Бейдж у навігації видно з будь-якої сторінки, тому лічильник
            // рахується тут, а не в NotificationController — той бачить
            // "непрочитане" лише той, хто вже й так відкрив "Сповіщення".
            // Через таблицю, а не Addons\Notifications\Models\Notification:
            // ядро ніде не імпортує класи аддонів (лише Settings/permissions
            // як спільні джерела), і Schema::hasTable() рятує від помилки,
            // якщо модуль Notifications ще не встановлено взагалі.
            'unreadNotifications' => $this->unreadNotificationsCount($request),
            'unreadMessages' => $this->unreadMessagesCount($request),
        ];
    }

    private function unreadNotificationsCount(Request $request): int
    {
        if (! $request->user() || ! Schema::hasTable('notifications')) {
            return 0;
        }

        return DB::table('notifications')
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Так само, як unreadNotificationsCount: рахується тут, бо бейдж видно
     * з будь-якої сторінки. Розмов на учасника завжди мало (сімейний чат +
     * жменька особистих), тому цикл по них — не проблема продуктивності.
     */
    private function unreadMessagesCount(Request $request): int
    {
        if (! $request->user() || ! Schema::hasTable('messages')) {
            return 0;
        }

        $userId = $request->user()->id;

        $familyId = DB::table('conversations')->where('type', 'family')->value('id');
        $directIds = DB::table('conversation_participants')->where('user_id', $userId)->pluck('conversation_id');
        $conversationIds = $directIds->when($familyId, fn ($c) => $c->push($familyId))->unique();

        if ($conversationIds->isEmpty()) {
            return 0;
        }

        $reads = DB::table('conversation_reads')
            ->where('user_id', $userId)
            ->whereIn('conversation_id', $conversationIds)
            ->pluck('last_read_message_id', 'conversation_id');

        $count = 0;
        foreach ($conversationIds as $conversationId) {
            $lastRead = $reads[$conversationId] ?? 0;
            $count += DB::table('messages')
                ->where('conversation_id', $conversationId)
                ->where('id', '>', $lastRead)
                // sender_id NULL — системне повідомлення (чат Monsory Finance);
                // у SQL "NULL != x" не істинне, тож без whereNull воно ніколи
                // не рахувалось би непрочитаним.
                ->where(fn ($q) => $q->whereNull('sender_id')->orWhere('sender_id', '!=', $userId))
                ->count();
        }

        return $count;
    }
}
