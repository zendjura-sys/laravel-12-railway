<?php

namespace App\Http\Controllers;

use App\Models\UnionComplaint;
use App\Models\UnionComplaintAttachment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Скарги з боку союзників: подати скаргу на когось з іншої родини союзу,
 * бачити історію своїх скарг і (якщо ти лідер/заступник) — скарги проти
 * ВЛАСНОЇ родини з можливістю їх розглянути. Повна модерація з боку
 * Monsory — Admin\UnionComplaintController.
 */
class UnionComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorizeUnionMember($request);
        $user = $request->user();

        $visibleIds = UnionComplaint::visibleTo($user)->pluck('id');

        $complaints = UnionComplaint::query()
            ->where(fn ($q) => $q->where('reporter_id', $user->id)->orWhereIn('id', $visibleIds))
            ->with(['reporter:id,name', 'reviewer:id,name', 'attachments'])
            ->latest()
            ->get();

        return Inertia::render('Union/Complaints', [
            'complaints' => $complaints,
            'reasons' => UnionComplaint::REASONS,
            'statuses' => UnionComplaint::STATUSES,
            'myId' => $user->id,
            'canReview' => $user->union_family_name && in_array($user->union_role, ['leader', 'deputy'], true),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeUnionMember($request);

        $data = $request->validate([
            'against_family' => ['required', 'string', 'max:150'],
            'against_name' => ['required', 'string', 'max:150'],
            'reasons' => ['required', 'array', 'min:1'],
            'reasons.*' => ['string', 'in:'.implode(',', array_keys(UnionComplaint::REASONS))],
            'description' => ['nullable', 'string', 'max:2000'],
            // Оригінал без стиснення — той самий підхід, що й у Reports.
            'photos' => ['nullable', 'array', 'max:30'],
            'photos.*' => ['file', 'mimes:jpeg,jpg,png,webp', 'max:20480'],
        ]);

        $photos = $data['photos'] ?? [];
        unset($data['photos']);

        $complaint = UnionComplaint::create([
            ...$data,
            'reporter_id' => $request->user()->id,
            'status' => 'pending',
        ]);

        foreach (array_values($photos) as $i => $photo) {
            $path = $photo->store('union/complaints', 'public');

            UnionComplaintAttachment::create([
                'union_complaint_id' => $complaint->id,
                'disk_path' => $path,
                'original_name' => $photo->getClientOriginalName(),
                'size' => $photo->getSize(),
                'position' => $i,
            ]);
        }

        return back()->with('status', 'Скаргу подано, очікує на розгляд.');
    }

    /** Лідер/заступник обвинуваченої родини або Monsory-адмін (union.manage). */
    public function update(Request $request, UnionComplaint $unionComplaint): RedirectResponse
    {
        $this->authorizeReview($request->user(), $unionComplaint);

        $data = $request->validate([
            'status' => ['required', Rule::in(UnionComplaint::STATUSES)],
            'reviewer_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $unionComplaint->update([
            ...$data,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('status', 'Статус скарги оновлено.');
    }

    /** Скарги — фіча лише для союзників, не для учасників самої Monsory. */
    private function authorizeUnionMember(Request $request): void
    {
        abort_unless($request->user()->union_family_name !== null, 403);
    }

    private function authorizeReview(User $user, UnionComplaint $complaint): void
    {
        if ($user->can('union.manage')) {
            return;
        }

        if (
            $user->union_family_name
            && in_array($user->union_role, ['leader', 'deputy'], true)
            && mb_strtolower(trim($user->union_family_name)) === mb_strtolower(trim($complaint->against_family))
        ) {
            return;
        }

        abort(403);
    }
}
