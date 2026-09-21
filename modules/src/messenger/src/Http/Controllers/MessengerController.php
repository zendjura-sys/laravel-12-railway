<?php

namespace Addons\Messenger\Http\Controllers;

use Addons\Messenger\Models\Conversation;
use Addons\Messenger\Models\ConversationParticipant;
use Addons\Messenger\Models\ConversationRead;
use Addons\Messenger\Models\Message;
use Addons\Messenger\Models\Sticker;
use Addons\Messenger\Models\UserIdentityKey;
use App\Models\Setting;
use App\Models\User;
use App\Support\MobilePushSender;
use App\Support\WebPushSender;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Один сімейний чат (усі зареєстровані, не тіньові) + особисті між двома.
 * Без черг/вебсокетів — Vue-сторінка чату сама опитує /messages?after_id=
 * кожні кілька секунд, поки відкрита. Для клану такого масштабу цього
 * достатньо, а Reverb/nginx-вебсокет — окрема інфраструктурна зміна, яку
 * можна додати пізніше, якщо знадобиться миттєвіша доставка.
 */
class MessengerController
{
    public function index(Request $request): Response
    {
        return Inertia::render('Messenger/Index', [
            'conversations' => $this->conversationListPayload($request->user()),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $payload = $this->conversationDetailPayload($request, $conversation);
        $payload['giphyEnabled'] = (bool) Setting::get('giphy_api_key');

        return Inertia::render('Messenger/Show', $payload);
    }

    /**
     * Список розмов для мобільного застосунку (Flutter) — той самий вміст,
     * що й index(), лише як JSON замість Inertia-сторінки.
     */
    protected function conversationListPayload(User $user): Collection
    {
        $family = $this->familyConversation();

        $directIds = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->pluck('conversation_id');

        $directs = Conversation::query()
            ->whereIn('id', $directIds)
            ->with(['participants.user:id,name,avatar_path'])
            ->withMax('messages', 'created_at')
            ->orderByDesc('messages_max_created_at')
            ->get();

        // Чат заступників — та сама ідея, що й family (один спільний
        // на всіх, хто підходить, без окремих conversation_participants),
        // тільки видимий не всім, а лише тим, у кого посада
        // "Заступник директора" (isDeputy()).
        $conversations = $this->isDeputy($user)
            ? collect([$family, $this->deputiesConversation()])->concat($directs)
            : collect([$family])->concat($directs);

        $reads = ConversationRead::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', $conversations->pluck('id'))
            ->pluck('last_read_message_id', 'conversation_id');

        return $conversations->map(function (Conversation $c) use ($user, $reads) {
            $lastMessage = Message::query()
                ->where('conversation_id', $c->id)
                ->with('sender:id,name')
                ->latest('id')
                ->first();

            $lastRead = $reads[$c->id] ?? 0;
            $unread = Message::query()
                ->where('conversation_id', $c->id)
                ->where('id', '>', $lastRead)
                ->where('sender_id', '!=', $user->id)
                ->count();

            $otherUser = $c->type === 'direct'
                ? $c->participants->pluck('user')->filter(fn ($u) => $u && $u->id !== $user->id)->first()
                : null;

            $title = match ($c->type) {
                'deputies' => 'Заступники',
                'direct' => $otherUser?->name ?? 'Учасник',
                default => 'Загальний чат родини',
            };

            return [
                'id' => $c->id,
                'type' => $c->type,
                'title' => $title,
                // Потрібен мобільному клієнту для наскрізного шифрування
                // (Фаза 1: лише direct) — щоб отримати публічний ключ
                // співрозмовника й вивести спільний секрет через ECDH.
                'otherUserId' => $otherUser?->id,
                'lastMessage' => $lastMessage ? [
                    'body' => $this->previewText($lastMessage),
                    'senderName' => $lastMessage->sender?->name,
                    'isMine' => $lastMessage->sender_id === $user->id,
                    'createdAt' => $lastMessage->created_at,
                ] : null,
                'unread' => $unread,
            ];
        })->sortByDesc(fn ($c) => $c['lastMessage']['createdAt'] ?? null)->values();
    }

    /**
     * Тред розмови (заголовок + останні 50 повідомлень), уже позначений
     * прочитаним — спільне для веб-сторінки Show і мобільного API.
     */
    protected function conversationDetailPayload(Request $request, Conversation $conversation): array
    {
        $this->ensureAccess($conversation, $request->user());

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->with('sender:id,name,avatar_path,position_key')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $this->markRead($request, $conversation);

        $otherUser = $conversation->type === 'direct'
            ? ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $request->user()->id)
                ->with('user:id,name,avatar_path')
                ->first()?->user
            : null;

        $title = match ($conversation->type) {
            'deputies' => 'Заступники',
            'direct' => $otherUser?->name ?? 'Учасник',
            default => 'Загальний чат родини',
        };

        return [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $title,
                'otherUserId' => $otherUser?->id,
            ],
            'messages' => $messages->map(fn (Message $m) => $this->formatMessage($m, $request->user()->id)),
            'myId' => $request->user()->id,
        ];
    }

    public function messagesSince(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureAccess($conversation, $request->user());

        $afterId = (int) $request->query('after_id', 0);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->where('id', '>', $afterId)
            ->with('sender:id,name,avatar_path,position_key')
            ->oldest('id')
            ->limit(100)
            ->get();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => [
                'messages' => $messages->map(fn (Message $m) => $this->formatMessage($m, $request->user()->id)),
            ],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function store(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureAccess($conversation, $request->user());

        $validated = $request->validate([
            'type' => ['nullable', Rule::in(['text', 'text_e2ee', 'photo', 'gif', 'sticker'])],
            'body' => ['nullable', 'string', 'max:4000'],
            'photo' => ['required_if:type,photo', 'nullable', 'image', 'max:8192'],
            'gif_url' => ['required_if:type,gif', 'nullable', 'url'],
            'sticker_id' => ['required_if:type,sticker', 'nullable', 'integer'],
        ]);

        $type = $validated['type'] ?? 'text';
        $body = trim($validated['body'] ?? '');
        $attachmentPath = null;
        $attachmentUrl = null;

        if (in_array($type, ['text', 'text_e2ee'], true) && $body === '') {
            throw ValidationException::withMessages(['body' => 'Повідомлення не може бути порожнім.']);
        }

        // Наскрізне шифрування (Фаза 1) — лише особисті розмови. Сервер
        // все одно не вміє й не пробує розшифрувати text_e2ee (body —
        // ціле зашифроване повідомлення для клієнта), тому це не про
        // безпеку, а про те, щоб такий тип не потрапляв туди, де його
        // ніхто не зможе розшифрувати (сімейний чат, чат заступників).
        if ($type === 'text_e2ee' && $conversation->type !== 'direct') {
            throw ValidationException::withMessages(['type' => 'Шифрування доступне лише в особистих розмовах.']);
        }

        if ($type === 'photo') {
            $attachmentPath = $request->file('photo')->store('messenger/photos', 'public');
        }

        if ($type === 'gif') {
            $attachmentUrl = $validated['gif_url'];
        }

        if ($type === 'sticker') {
            $sticker = Sticker::where('user_id', $request->user()->id)->find($validated['sticker_id']);
            if (! $sticker) {
                throw ValidationException::withMessages(['sticker_id' => 'Стікер не знайдено.']);
            }
            $attachmentPath = $sticker->path;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => $body,
            'type' => $type,
            'attachment_path' => $attachmentPath,
            'attachment_url' => $attachmentUrl,
        ]);
        $message->load('sender:id,name,avatar_path,position_key');

        $conversation->touch();

        ConversationRead::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id],
            ['last_read_message_id' => $message->id],
        );

        $this->notifyNewMessage($conversation, $message, $request->user());

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['message' => $this->formatMessage($message, $request->user()->id)],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    /** Власна бібліотека стікерів того, хто питає — не спільна для всіх. */
    public function stickers(Request $request): JsonResponse
    {
        $stickers = Sticker::where('user_id', $request->user()->id)->latest()->get();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['stickers' => $stickers],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function storeSticker(Request $request): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'image', 'max:2048'],
        ]);

        $path = $request->file('image')->store('stickers', 'public');
        $sticker = Sticker::create(['user_id' => $request->user()->id, 'path' => $path]);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['sticker' => $sticker],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function destroySticker(Request $request, Sticker $sticker): JsonResponse
    {
        if ($sticker->user_id !== $request->user()->id) {
            throw new AccessDeniedHttpException;
        }

        Storage::disk('public')->delete($sticker->path);
        $sticker->delete();

        return response()->json(['ok' => true, 'message' => null, 'data' => null, 'errors' => null, 'redirect' => null]);
    }

    /**
     * Проксі до Giphy — ключ лишається на бекенді (Admin → API-ключі),
     * ніколи не потрапляє на фронт. Без ключа фіча просто вимкнена
     * (giphyEnabled: false в конфігу сторінки), сюди запит не дійде.
     */
    public function searchGifs(Request $request): JsonResponse
    {
        $apiKey = Setting::get('giphy_api_key');
        if (! $apiKey) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['gifs' => []], 'errors' => null, 'redirect' => null]);
        }

        $query = trim((string) $request->query('q', ''));
        $endpoint = $query === '' ? 'trending' : 'search';

        $response = Http::timeout(6)->get("https://api.giphy.com/v1/gifs/{$endpoint}", array_filter([
            'api_key' => $apiKey,
            'q' => $query === '' ? null : $query,
            'limit' => 24,
            'rating' => 'pg-13',
        ]));

        if (! $response->successful()) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['gifs' => []], 'errors' => null, 'redirect' => null]);
        }

        $gifs = collect($response->json('data', []))->map(fn ($gif) => [
            'id' => $gif['id'],
            'previewUrl' => $gif['images']['fixed_width_small']['url'] ?? $gif['images']['fixed_width']['url'] ?? null,
            'url' => $gif['images']['original']['url'] ?? null,
        ])->filter(fn ($gif) => $gif['previewUrl'] && $gif['url'])->values();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['gifs' => $gifs],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->ensureAccess($conversation, $request->user());

        $lastId = Message::query()->where('conversation_id', $conversation->id)->max('id');

        if ($lastId) {
            ConversationRead::updateOrCreate(
                ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id],
                ['last_read_message_id' => $lastId],
            );
        }

        return response()->json(['ok' => true, 'message' => null, 'data' => null, 'errors' => null, 'redirect' => null]);
    }

    public function searchMembers(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['members' => []], 'errors' => null, 'redirect' => null]);
        }

        $members = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('is_shadow', false)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name', 'avatar_path']);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['members' => $members],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function startDirect(Request $request, User $target): RedirectResponse
    {
        $conversation = $this->resolveDirectConversation($request->user(), $target);

        return redirect()->route('messenger.show', $conversation->id);
    }

    /**
     * Знаходить наявну особисту розмову між двома учасниками або створює
     * нову — спільне для веб-редіректу і JSON-відповіді мобільного API.
     */
    protected function resolveDirectConversation(User $user, User $target): Conversation
    {
        if ($target->id === $user->id || $target->is_shadow) {
            throw ValidationException::withMessages(['target' => 'Неможливо почати чат із цим користувачем.']);
        }

        $existingId = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereIn('conversation_id', function ($q) use ($target) {
                $q->select('conversation_id')
                    ->from('conversation_participants')
                    ->where('user_id', $target->id);
            })
            ->whereHas('conversation', fn ($q) => $q->where('type', 'direct'))
            ->value('conversation_id');

        if ($existingId) {
            return Conversation::findOrFail($existingId);
        }

        $conversation = Conversation::create(['type' => 'direct']);
        ConversationParticipant::insert([
            ['conversation_id' => $conversation->id, 'user_id' => $user->id, 'created_at' => now(), 'updated_at' => now()],
            ['conversation_id' => $conversation->id, 'user_id' => $target->id, 'created_at' => now(), 'updated_at' => now()],
        ]);

        return $conversation;
    }

    protected function familyConversation(): Conversation
    {
        return Conversation::firstOrCreate(['type' => 'family']);
    }

    protected function deputiesConversation(): Conversation
    {
        return Conversation::firstOrCreate(['type' => 'deputies']);
    }

    /**
     * "Заступник" тут — конкретна посада ("Заступник директора" у
     * FamilyContent::positions(), ключ deputy-director), а не окрема
     * spatie-роль: група чату для заступників має слідувати за тим самим
     * призначенням посади, яким адмін уже керує в Адмін → Учасники, без
     * додаткового окремого перемикача.
     */
    protected function isDeputy(User $user): bool
    {
        return $user->position_key === 'deputy-director';
    }

    protected function ensureAccess(Conversation $conversation, User $user): void
    {
        if ($conversation->type === 'family') {
            return;
        }

        if ($conversation->type === 'deputies') {
            if (! $this->isDeputy($user)) {
                throw new AccessDeniedHttpException;
            }

            return;
        }

        $isParticipant = ConversationParticipant::query()
            ->where('conversation_id', $conversation->id)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isParticipant) {
            throw new AccessDeniedHttpException;
        }
    }

    /**
     * Push у мобільний застосунок і в браузер про нове повідомлення —
     * раніше цього не було зовсім (чат покладався лише на бейдж
     * непрочитаного й опитування відкритої сторінки), тому користувач
     * дізнавався про нове повідомлення, лише сам відкривши месенджер.
     * Обидва відправники мовчки no-op, якщо канал не налаштовано
     * (немає VAPID-ключів / службового акаунта Firebase) — так само,
     * як у решті застосунку.
     */
    protected function notifyNewMessage(Conversation $conversation, Message $message, User $sender): void
    {
        $recipientIds = $this->pushRecipientIds($conversation, $sender->id);
        if ($recipientIds->isEmpty()) {
            return;
        }

        $title = match ($conversation->type) {
            'family' => $sender->name.' · Родина',
            'deputies' => $sender->name.' · Заступники',
            default => $sender->name,
        };
        $body = $this->previewText($message);
        $url = '/messenger/'.$conversation->id;

        (new MobilePushSender())->sendToUserIds($recipientIds, $title, $body, $url);

        $recipients = User::query()->whereIn('id', $recipientIds)->get(['id']);
        $webPush = new WebPushSender();
        foreach ($recipients as $recipient) {
            $webPush->sendToUser($recipient, $title, $body, $url);
        }
    }

    /** @return Collection<int,int> */
    protected function pushRecipientIds(Conversation $conversation, int $senderId): Collection
    {
        return match ($conversation->type) {
            'family' => User::query()->where('is_shadow', false)->where('id', '!=', $senderId)->pluck('id'),
            'deputies' => User::query()->where('position_key', 'deputy-director')->where('id', '!=', $senderId)->pluck('id'),
            default => ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $senderId)
                ->pluck('user_id'),
        };
    }

    /** Короткий підпис для списку розмов — фото/gif/стікер без тексту не мають порожнього рядка замість прев'ю. */
    protected function previewText(Message $m): string
    {
        return match ($m->type) {
            'photo' => $m->body !== '' ? '📷 '.$m->body : '📷 Фото',
            'gif' => '🎞 GIF',
            'sticker' => '🙂 Стікер',
            // body тут — зашифрований блок, не текст; показувати його як
            // прев'ю не можна (і незрозуміло людині, і сервер сам не вміє
            // його прочитати, щоб перевірити).
            'text_e2ee' => '🔒 Зашифроване повідомлення',
            default => $m->body,
        };
    }

    /**
     * Публікує/оновлює власний публічний X25519-ключ — викликається
     * мобільним клієнтом один раз при першому запуску (чи після
     * перевстановлення, коли генерується нова пара ключів). updateOrCreate,
     * не create: новий пристрій/перевстановлення просто заміняє ключ,
     * старі зашифровані повідомлення після цього нечитабельні — це
     * свідомо прийнятий компроміс Фази 1 (без окремого бекапу ключів).
     */
    public function publishIdentityKey(Request $request): JsonResponse
    {
        $data = $request->validate(['public_key' => ['required', 'string', 'max:255']]);

        UserIdentityKey::updateOrCreate(
            ['user_id' => $request->user()->id],
            ['public_key' => $data['public_key']],
        );

        return response()->json(['ok' => true, 'message' => null, 'data' => null, 'errors' => null, 'redirect' => null]);
    }

    /**
     * Публічний ключ будь-якого зареєстрованого учасника — потрібен ДО
     * початку листування (щоб вивести спільний секрет через ECDH), тому
     * доступ не гейтиться участю в розмові з цим користувачем, так само
     * як searchMembers() вище.
     */
    public function identityKey(Request $request, User $user): JsonResponse
    {
        $publicKey = UserIdentityKey::query()->where('user_id', $user->id)->value('public_key');

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['publicKey' => $publicKey],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    protected function formatMessage(Message $m, int $myId): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'type' => $m->type,
            'attachmentUrl' => $m->attachment_url,
            'senderId' => $m->sender_id,
            'senderName' => $m->sender?->name,
            'senderPosition' => $m->sender?->position_title,
            'isMine' => $m->sender_id === $myId,
            'createdAt' => $m->created_at,
        ];
    }
}
