<?php

namespace Addons\FamilyEvents\Http\Controllers\Api;

use Addons\FamilyEvents\Events\FamilyEventsDigestRequested;
use Addons\FamilyEvents\Http\Controllers\Admin\FamilyEventsAdminController as WebFamilyEventsAdminController;
use Addons\FamilyEvents\Models\FamilyEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;

/**
 * Мобільний двійник Admin\FamilyEventsAdminController — переиспользує
 * createEvent()/validated() замість дублювання. aiDraft() з батьківського
 * класу вже повертає JSON — reuse напряму через окремий маршрут /api.
 */
class AdminFamilyEventsController extends WebFamilyEventsAdminController
{
    public function indexJson(): JsonResponse
    {
        $events = FamilyEvent::query()
            ->withCount([
                'rsvps as going_count' => fn ($q) => $q->where('status', 'going'),
                'rsvps as not_going_count' => fn ($q) => $q->where('status', 'not_going'),
            ])
            ->orderByDesc('starts_at')
            ->get()
            ->map(fn (FamilyEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'startsAt' => $event->starts_at,
                'goingCount' => $event->going_count,
                'notGoingCount' => $event->not_going_count,
                'isPast' => $event->isPast(),
            ]);

        return response()->json(['events' => $events]);
    }

    public function storeJson(Request $request): JsonResponse
    {
        $event = $this->createEvent($this->validated($request), $request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Подію створено.',
            'data' => ['id' => $event->id],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function destroyJson(FamilyEvent $familyEvent): JsonResponse
    {
        $familyEvent->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Подію видалено.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function sendDigestJson(): JsonResponse
    {
        Event::dispatch(new FamilyEventsDigestRequested());

        return response()->json([
            'ok' => true,
            'message' => 'Дайджест подій надіслано.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
