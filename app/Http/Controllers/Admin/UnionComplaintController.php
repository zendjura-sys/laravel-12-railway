<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnionComplaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Модерація скарг союзу з боку Monsory-адміністрації. Лідери/заступники
 * обвинуваченої родини теж можуть переглядати й закривати скарги проти
 * своєї родини — та гілка живе в кабінеті союзника (UnionCabinetController),
 * не тут: тут — лише повний доступ union.manage.
 */
class UnionComplaintController extends Controller
{
    public function index(Request $request): Response
    {
        $status = $request->query('status');

        $complaints = UnionComplaint::query()
            ->with(['reporter:id,name', 'reviewer:id,name', 'attachments'])
            ->when($status, fn ($q) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('Admin/Union/Complaints', [
            'complaints' => $complaints,
            'reasons' => UnionComplaint::REASONS,
            'statuses' => UnionComplaint::STATUSES,
            'filters' => ['status' => $status],
        ]);
    }

    public function update(Request $request, UnionComplaint $unionComplaint): RedirectResponse
    {
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
}
