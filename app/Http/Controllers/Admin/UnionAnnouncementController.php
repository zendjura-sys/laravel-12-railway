<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnionAnnouncement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UnionAnnouncementController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Union/Announcements', [
            'announcements' => UnionAnnouncement::query()
                ->with('author:id,name')
                ->orderByDesc('published_at')
                ->paginate(20),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        UnionAnnouncement::create([
            ...$data,
            'author_id' => $request->user()->id,
            'published_at' => $data['published_at'] ?? now(),
        ]);

        return back()->with('status', 'Оголошення опубліковано.');
    }

    public function update(Request $request, UnionAnnouncement $unionAnnouncement): RedirectResponse
    {
        $data = $this->validated($request);

        $unionAnnouncement->update([
            ...$data,
            'published_at' => $data['published_at'] ?? $unionAnnouncement->published_at,
        ]);

        return back()->with('status', 'Оголошення оновлено.');
    }

    public function destroy(UnionAnnouncement $unionAnnouncement): RedirectResponse
    {
        $unionAnnouncement->delete();

        return back()->with('status', 'Оголошення видалено.');
    }

    /** @return array{title:string,body:string,published_at:?string} */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
