<?php

namespace Addons\Notifications\Http\Controllers\Api;

use Addons\Notifications\Http\Controllers\Admin\BroadcastController as WebBroadcastController;
use Addons\Notifications\Models\Broadcast;
use App\Support\FamilyContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * Мобільний двійник Admin\BroadcastController — успадковує publish()
 * (рахує аудиторію, заводить Broadcast, bulk-insert у notifications,
 * Telegram-доставка) замість дублювання. polish() з батьківського класу
 * вже повертає JSON — reuse напряму через окремий маршрут /api.
 */
class AdminBroadcastController extends WebBroadcastController
{
    /** Ролі й посади для вибору аудиторії в застосунку — той самий список, що на сайті. */
    public function audienceOptionsJson(): JsonResponse
    {
        return response()->json([
            'roles' => Role::query()->pluck('name'),
            'positions' => collect(FamilyContent::positions())
                ->map(fn ($p) => ['key' => $p['key'], 'title' => $p['title']])
                ->values(),
        ]);
    }

    public function recentJson(Request $request): JsonResponse
    {
        $broadcasts = Broadcast::query()
            ->with('creator:id,name')
            ->orderByDesc('pinned')
            ->latest()
            ->limit(15)
            ->get()
            ->map(fn (Broadcast $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'body' => $b->body,
                'pinned' => $b->pinned,
                'recipientsCount' => $b->recipients_count,
                'creatorName' => $b->creator?->name,
                'createdAt' => $b->created_at,
            ]);

        return response()->json(['broadcasts' => $broadcasts]);
    }

    public function storeJson(Request $request): JsonResponse
    {
        $broadcast = $this->publish($this->validated($request), $request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Розсилку опубліковано.',
            'data' => ['id' => $broadcast->id],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
