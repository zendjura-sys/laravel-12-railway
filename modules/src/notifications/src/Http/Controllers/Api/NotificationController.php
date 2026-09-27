<?php

namespace Addons\Notifications\Http\Controllers\Api;

use Addons\Notifications\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON-двійник веб-NotificationController для мобільного застосунку.
 * markRead()/markAllRead() там уже повертають чистий JSON без жодної
 * Inertia-специфіки — reuse через окремі /api-маршрути на ті самі методи,
 * лише index() отримує JSON-варіант (веб-версія — Inertia\Response).
 */
class NotificationController
{
    public function indexJson(Request $request): JsonResponse
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(50)
            ->get();

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => Notification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }
}
