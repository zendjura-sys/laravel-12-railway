<?php

namespace Addons\FamilyEvents\Http\Controllers;

use Addons\FamilyEvents\Models\FamilyEvent;
use Inertia\Inertia;
use Inertia\Response;

class FamilyEventsController
{
    public function index(): Response
    {
        $events = FamilyEvent::query()
            ->where('starts_at', '>=', FamilyEvent::nowAsStored())
            ->orderBy('starts_at')
            ->get(['id', 'title', 'description', 'location', 'starts_at']);

        return Inertia::render('Events/Index', [
            'events' => $events,
        ]);
    }
}
