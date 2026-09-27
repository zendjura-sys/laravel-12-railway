<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChangelogEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * «Що нового» — те саме, чим для розділів сайту є FamilyContent: не
 * захардкожений список у Vue, який тільки я можу поповнити, а таблиця,
 * яку веде сам адмін після кожного релізу.
 */
class ChangelogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Changelog/Index', [
            'entries' => ChangelogEntry::query()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'published_at' => ['required', 'date'],
        ]);

        ChangelogEntry::create([...$data, 'created_by' => $request->user()->id]);

        return back()->with('status', 'Запис додано.');
    }

    public function update(Request $request, ChangelogEntry $changelogEntry): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'published_at' => ['required', 'date'],
        ]);

        $changelogEntry->update($data);

        return back()->with('status', 'Запис оновлено.');
    }

    public function destroy(ChangelogEntry $changelogEntry): RedirectResponse
    {
        $changelogEntry->delete();

        return back()->with('status', 'Запис видалено.');
    }
}
