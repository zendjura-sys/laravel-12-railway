<?php

namespace Addons\Messenger\Http\Controllers;

use Addons\Messenger\Models\Conversation;
use Addons\Messenger\Models\ConversationParticipant;
use Addons\Messenger\Models\ConversationRead;
use Addons\Messenger\Models\Message;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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

        $conversations = collect([$family])->concat($directs);

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

            $title = 'Загальний чат родини';
            if ($c->type === 'direct') {
                $other = $c->participants->pluck('user')->filter(fn ($u) => $u && $u->id !== $user->id)->first();
                $title = $other?->name ?? 'Учасник';
            }

            return [
                'id' => $c->id,
                'type' => $c->type,
                'title' => $title,
                'lastMessage' => $lastMessage ? [
                    'body' => $lastMessage->body,
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
            ->with('sender:id,name,avatar_path')
            ->latest('id')
            ->limit(50)
            ->get()
            ->reverse()
            ->values();

        $this->markRead($request, $conversation);

        $title = 'Загальний чат родини';
        if ($conversation->type === 'direct') {
            $other = ConversationParticipant::query()
                ->where('conversation_id', $conversation->id)
                ->where('user_id', '!=', $request->user()->id)
                ->with('user:id,name,avatar_path')
                ->first()?->user;
            $title = $other?->name ?? 'Учасник';
        }

        return [
            'conversation' => [
                'id' => $conversation->id,
                'type' => $conversation->type,
                'title' => $title,
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
            ->with('sender:id,name,avatar_path')
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
            'body' => ['required', 'string', 'max:4000'],
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => $request->user()->id,
            'body' => trim($validated['body']),
        ]);
        $message->load('sender:id,name,avatar_path');

        $conversation->touch();

        ConversationRead::updateOrCreate(
            ['conversation_id' => $conversation->id, 'user_id' => $request->user()->id],
            ['last_read_message_id' => $message->id],
        );

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['message' => $this->formatMessage($message, $request->user()->id)],
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

    protected function ensureAccess(Conversation $conversation, User $user): void
    {
        if ($conversation->type === 'family') {
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

    protected function formatMessage(Message $m, int $myId): array
    {
        return [
            'id' => $m->id,
            'body' => $m->body,
            'senderId' => $m->sender_id,
            'senderName' => $m->sender?->name,
            'isMine' => $m->sender_id === $myId,
            'createdAt' => $m->created_at,
        ];
    }
}
