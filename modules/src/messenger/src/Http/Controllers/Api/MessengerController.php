<?php

namespace Addons\Messenger\Http\Controllers\Api;

use Addons\Messenger\Http\Controllers\MessengerController as WebMessengerController;
use Addons\Messenger\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON-дзеркало веб-контролера для мобільного застосунку (Flutter).
 * index()/show() у батьківському класі повертають Inertia\Response —
 * тому тут окремі методи (не override, щоб не зіштовхнутись з
 * несумісністю типів повернення), що перевикористовують ту саму
 * бізнес-логіку через protected-методи. store()/messagesSince()/
 * markRead()/searchMembers() уже повертають JSON і використовуються
 * як є — окремий маршрут /api/messenger/... просто вказує на той самий
 * успадкований метод.
 */
class MessengerController extends WebMessengerController
{
    public function indexJson(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['conversations' => $this->conversationListPayload($request->user())],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function showJson(Request $request, Conversation $conversation): JsonResponse
    {
        $payload = $this->conversationDetailPayload($request, $conversation);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => $payload,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function startDirectJson(Request $request, User $target): JsonResponse
    {
        $conversation = $this->resolveDirectConversation($request->user(), $target);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['conversationId' => $conversation->id],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
