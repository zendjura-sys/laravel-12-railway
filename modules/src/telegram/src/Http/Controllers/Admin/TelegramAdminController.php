<?php

namespace Addons\TelegramBot\Http\Controllers\Admin;

use Addons\TelegramBot\Models\TelegramApplication;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TelegramAdminController
{
    public function index(TelegramClient $telegram): Response
    {
        return Inertia::render('Admin/Telegram/Index', [
            'configured' => $telegram->isConfigured(),
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
     * Решение по заявке. Ответ уходит человеку в тот же чат, из которого
     * заявка пришла: заставлять его самого заходить и проверять статус —
     * ровно то, чего мы избегали, делая бота кнопочным.
     */
    public function review(Request $request, TelegramClient $telegram, TelegramApplication $application): JsonResponse
    {
        $data = $request->validate([
            'decision' => ['required', 'in:approve,reject'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        if (! $application->isPending()) {
            return $this->json(false, 'Цю заявку вже розглянули.');
        }

        $approved = $data['decision'] === 'approve';

        $application->update([
            'status' => $approved ? TelegramApplication::STATUS_APPROVED : TelegramApplication::STATUS_REJECTED,
            'review_note' => $data['note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $note = trim((string) ($data['note'] ?? ''));

        $text = $approved
            ? "◆  <b>ЗАЯВКУ СХВАЛЕНО</b>\n━━━━━━━━━━━━━━━\n\nВітаємо в Monsory Family, <b>".e($application->nickname)."</b>.\n\nЗ вами звʼяжеться керівництво щодо наступних кроків."
            : "◆  <b>ЗАЯВКУ ВІДХИЛЕНО</b>\n━━━━━━━━━━━━━━━\n\nДякуємо за інтерес до Monsory Family.\nЦього разу не склалося — подати нову заявку можна будь-коли.";

        if ($note !== '') {
            $text .= "\n\n<b>Коментар:</b> ".e($note);
        }

        $delivered = $telegram->sendMessage($application->chat_id, $text) !== null;

        return $this->json(
            true,
            ($approved ? 'Заявку схвалено.' : 'Заявку відхилено.')
                .($delivered ? ' Повідомлення надіслано.' : ' Повідомлення в Telegram надіслати не вдалося.'),
        );
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
