<?php

namespace Addons\FamilyEvents\Http\Controllers\Admin;

use Addons\FamilyEvents\Events\FamilyEventCreated;
use Addons\FamilyEvents\Models\FamilyEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Inertia\Inertia;
use Inertia\Response;

class FamilyEventsAdminController
{
    public function index(): Response
    {
        $events = FamilyEvent::query()
            ->with('creator:id,name')
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'location' => ['nullable', 'string', 'max:150'],
            'starts_at' => ['required', 'date'],
        ]);

        $event = FamilyEvent::create([
            ...$data,
            'created_by' => $request->user()->id,
        ]);

        Event::dispatch(new FamilyEventCreated($event));

        return back()->with('success', 'Подію створено.');
    }

    public function destroy(FamilyEvent $familyEvent): RedirectResponse
    {
        $familyEvent->delete();

        return back()->with('success', 'Подію видалено.');
    }
}
