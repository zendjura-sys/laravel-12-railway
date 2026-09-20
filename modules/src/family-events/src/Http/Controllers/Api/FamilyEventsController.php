<?php

namespace Addons\FamilyEvents\Http\Controllers\Api;

use Addons\FamilyEvents\Http\Controllers\FamilyEventsController as WebFamilyEventsController;
use Addons\FamilyEvents\Models\FamilyEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Мобільний двійник FamilyEventsController — rsvp() з батьківського класу
 * переиспользується як є (той самий toggle-запис у family_event_rsvps),
 * лише повертаємо JSON із актуальним статусом замість редіректу.
 */
class FamilyEventsController extends WebFamilyEventsController
{
    public function indexJson(Request $request): JsonResponse
    {
        $events = FamilyEvent::query()
            ->where('starts_at', '>=', FamilyEvent::nowAsStored())
            ->withCount(['rsvps as going_count' => fn ($q) => $q->where('status', 'going')])
            ->with(['rsvps' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'description', 'location', 'starts_at'])
            ->map(fn (FamilyEvent $event) => [
                'id' => $event->id,
                'title' => $event->title,
                'description' => $event->description,
                'location' => $event->location,
                'startsAt' => $event->starts_at,
                'goingCount' => $event->going_count,
                'myRsvp' => $event->rsvps->first()?->status,
            ]);

        return response()->json(['events' => $events]);
    }

    public function rsvpJson(Request $request, FamilyEvent $event): JsonResponse
    {
        $this->rsvp($request, $event);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['myRsvp' => $event->rsvps()->where('user_id', $request->user()->id)->value('status')],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
