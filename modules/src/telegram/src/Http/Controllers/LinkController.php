<?php

namespace Addons\TelegramBot\Http\Controllers;

use Addons\TelegramBot\Models\TelegramLink;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LinkController
{
    public function status(Request $request): JsonResponse
    {
        $link = TelegramLink::forUser($request->user()->id);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => [
                'linked' => $link->isLinked(),
                'telegram_username' => $link->telegram_username,
                'pending_code' => (! $link->isLinked() && $link->link_code && $link->code_expires_at?->isFuture()) ? $link->link_code : null,
                'bot_username' => Setting::get('telegram_bot_username'),
            ],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function generateCode(Request $request): JsonResponse
    {
        $link = TelegramLink::forUser($request->user()->id);
        $code = $link->generateCode();

        return response()->json([
            'ok' => true,
            'message' => 'Код згенеровано, дійсний 10 хвилин.',
            'data' => ['code' => $code, 'bot_username' => Setting::get('telegram_bot_username')],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function unlink(Request $request): JsonResponse
    {
        TelegramLink::query()->where('user_id', $request->user()->id)->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Telegram відвʼязано.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
