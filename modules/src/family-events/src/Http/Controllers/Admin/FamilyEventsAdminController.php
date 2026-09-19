<?php

namespace Addons\FamilyEvents\Http\Controllers\Admin;

use Addons\AiAssistant\Services\EventDraftAssistant;
use Addons\FamilyEvents\Events\FamilyEventCreated;
use Addons\FamilyEvents\Events\FamilyEventsDigestRequested;
use Addons\FamilyEvents\Models\FamilyEvent;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
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
            ->with(['creator:id,name,gender', 'rsvps.user:id,name'])
            ->withCount([
                'rsvps as going_count' => fn ($q) => $q->where('status', 'going'),
                'rsvps as not_going_count' => fn ($q) => $q->where('status', 'not_going'),
            ])
            ->orderByDesc('starts_at')
            ->get();

        return Inertia::render('Admin/Events/Index', [
            'events' => $events,
            'aiEventDraftEnabled' => class_exists(EventDraftAssistant::class) && Setting::get('ai_event_draft_enabled') === '1',
        ]);
    }

    /**
     * Ручна розсилка дайджесту всіх майбутніх подій — окремо від
     * автоматичних нагадувань по кожній події, на випадок коли адміну
     * треба разово нагадати всім про весь список.
     */
    public function sendDigest(): RedirectResponse
    {
        Event::dispatch(new FamilyEventsDigestRequested());

        return back()->with('success', 'Дайджест подій надіслано.');
    }

    /**
     * Чернетка назви й опису за короткою підказкою — адмін бачить готові
     * поля й редагує чи прибирає перед сабмітом, нічого не створює сам.
     */
    public function aiDraft(Request $request): JsonResponse
    {
        if (! class_exists(EventDraftAssistant::class)) {
            return response()->json([
                'ok' => false,
                'message' => 'Модуль AI Assistant не встановлено.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $data = $request->validate(['hint' => ['required', 'string', 'max:500']]);

        $draft = app(EventDraftAssistant::class)->draft($data['hint']);

        return response()->json([
            'ok' => $draft !== null,
            'message' => $draft !== null ? null : 'Не вдалося згенерувати — перевірте налаштування AI.',
            'data' => $draft,
            'errors' => null,
            'redirect' => null,
        ], $draft !== null ? 200 : 422);
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
