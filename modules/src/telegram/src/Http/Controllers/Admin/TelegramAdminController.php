<?php

namespace Addons\TelegramBot\Http\Controllers\Admin;

use Addons\TelegramBot\Models\TelegramApplication;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\ApplicationReview;
use Addons\TelegramBot\Services\FamilyGroup;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAdminController
{
    public function index(TelegramClient $telegram, FamilyGroup $group): Response
    {
        return Inertia::render('Admin/Telegram/Index', [
            'configured' => $telegram->isConfigured(),
            'group' => $this->groupStatus($telegram, $group),
            'botUsername' => Setting::get('telegram_bot_username'),
            'webhookInfo' => $telegram->getWebhookInfo(),
            'linkedCount' => TelegramLink::query()->whereNotNull('linked_at')->count(),
            'applications' => TelegramApplication::query()
                ->with('reviewer:id,name')
                // Заявки на рассмотрении всегда сверху: разобранные нужны
                // как история, а не как список дел.
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (TelegramApplication $a) => [
                    'id' => $a->id,
                    'nickname' => $a->nickname,
                    'telegram_username' => $a->telegram_username,
                    'age_range' => $a->age_range,
                    'playtime' => $a->playtime,
                    'experience' => $a->experience,
                    'direction' => $a->direction,
                    'about' => $a->about,
                    'status' => $a->status,
                    'review_note' => $a->review_note,
                    'reviewer' => $a->reviewer?->name,
                    'created_at' => $a->created_at?->format('d.m.Y H:i'),
                    'reviewed_at' => $a->reviewed_at?->format('d.m.Y H:i'),
                ]),
            'pendingCount' => TelegramApplication::query()
                ->where('status', TelegramApplication::STATUS_PENDING)->count(),
        ]);
    }

    public function setupWebhook(Request $request, TelegramClient $telegram): JsonResponse
    {
        $secret = Str::random(32);
        $url = route('telegram.webhook', ['secret' => $secret]);

        $result = $telegram->setWebhook($url, $secret);

        if ($result['ok']) {
            Setting::set('telegram_webhook_secret', $secret, 'telegram');
        }

        return $this->json($result['ok'], $result['ok'] ? 'Webhook встановлено.' : ('Помилка: '.$result['message']));
    }

    public function removeWebhook(TelegramClient $telegram): JsonResponse
    {
        $result = $telegram->deleteWebhook();
        Setting::set('telegram_webhook_secret', null, 'telegram');

        return $this->json($result['ok'], $result['ok'] ? 'Webhook знято.' : ('Помилка: '.$result['message']));
    }

    /**
     * Решение по заявке. Сама логика — в ApplicationReview: то же решение
     * принимают кнопками прямо в чате бота, и расходиться этим двум путям
     * нельзя.
     */
    public function review(Request $request, ApplicationReview $review, TelegramApplication $application): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $review->decide(
            $application,
            $request->user(),
            $data['decision'] === 'approve',
            $data['note'] ?? null,
        );

        return $this->json($result['ok'], $result['message']);
    }

    /**
     * Готова ли группа принимать людей. Проверяем именно через getChat:
     * записанный в настройках ID может быть опечаткой или чатом, куда
     * бота не добавили, и выяснять это в момент одобрения заявки поздно.
     *
     * @return array<string,mixed>
     */
    private function groupStatus(TelegramClient $telegram, FamilyGroup $group): array
    {
        $id = $group->id();

        if ($id === null) {
            return ['id' => null, 'ok' => false, 'title' => null, 'error' => 'Не вказано ID групи.'];
        }

        if (! $telegram->isConfigured()) {
            return ['id' => $id, 'ok' => false, 'title' => null, 'error' => 'Не вказано Bot Token.'];
        }

        $info = $telegram->chatInfo($id);

        return [
            'id' => $id,
            'ok' => $info['ok'],
            'title' => $info['title'],
            'error' => $info['ok'] ? null : $info['message'],
        ];
    }

    private function json(bool $ok, string $message): JsonResponse
    {
        return response()->json([
            'ok' => $ok,
            'message' => $message,
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
