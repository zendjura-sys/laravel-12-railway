<?php

namespace Addons\FamilyEvents\Http\Controllers;

use Addons\FamilyEvents\Models\FamilyEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FamilyEventsController
{
    public function index(Request $request): Response
    {
        $events = FamilyEvent::query()
            ->where('starts_at', '>=', FamilyEvent::nowAsStored())
            ->withCount([
                'rsvps as going_count' => fn ($q) => $q->where('status', 'going'),
            ])
            ->with(['rsvps' => fn ($q) => $q->where('user_id', $request->user()->id)])
            ->orderBy('starts_at')
            ->get(['id', 'title', 'description', 'location', 'starts_at'])
            ->map(function (FamilyEvent $event) {
                return [
                    'id' => $event->id,
                    'title' => $event->title,
                    'description' => $event->description,
                    'location' => $event->location,
                    'starts_at' => $event->starts_at,
                    'going_count' => $event->going_count,
                    'my_rsvp' => $event->rsvps->first()?->status,
                ];
            });

        return Inertia::render('Events/Index', [
            'events' => $events,
        ]);
    }

    public function rsvp(Request $request, FamilyEvent $event): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:going,not_going'],
        ]);

        $user = $request->user();
        $existing = $event->rsvps()->where('user_id', $user->id)->first();

        if ($existing && $existing->status === $data['status']) {
            $existing->delete();
        } else {
            $event->rsvps()->updateOrCreate(['user_id' => $user->id], ['status' => $data['status']]);
        }

        return back();
    }
}
