<?php

namespace Addons\Notifications\Http\Controllers;

use Addons\Notifications\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController
{
    public function index(Request $request): Response
    {
        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'unreadCount' => Notification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count(),
        ]);
    }

    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'message' => 'Усі сповіщення позначено прочитаними.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        $notification->delete();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    /**
     * Масове видалення власних сповіщень (мобільний режим вибору):
     * {ids: [...]} — вибрані, {all: true} — усі. Чужі id мовчки
     * ігноруються — фільтр за user_id у самому запиті.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'all' => ['sometimes', 'boolean'],
            'ids' => ['required_without:all', 'array', 'max:500'],
            'ids.*' => ['integer'],
        ]);

        $deleted = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when(! ($data['all'] ?? false), fn ($q) => $q->whereIn('id', $data['ids'] ?? []))
            ->delete();

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['deleted' => $deleted],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
