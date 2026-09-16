<?php

namespace Addons\Notifications\Http\Controllers\Admin;

use Addons\Notifications\Models\Broadcast;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class BroadcastController
{
    public function index(): Response
    {
        $broadcasts = Broadcast::query()
            ->with('creator:id,name')
            ->orderByDesc('pinned')
            ->latest()
            ->paginate(15);

        return Inertia::render('Admin/Broadcasts/Index', [
            'broadcasts' => $broadcasts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:4000'],
            'pinned' => ['boolean'],
        ]);

        $broadcast = Broadcast::create([
            ...$data,
            'pinned' => $data['pinned'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        // Особиста копія кожному учаснику одним bulk-insert'ом, а не N
        // окремих запитів — родина зростає, а не лишається на 5 акаунтах.
        $now = now();
        User::query()->select('id')->chunk(200, function ($users) use ($broadcast, $now) {
            DB::table('notifications')->insert($users->map(fn (User $user) => [
                'user_id' => $user->id,
                'type' => 'broadcast',
                'title' => $broadcast->title,
                'body' => $broadcast->body,
                'read_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all());
        });

        return back()->with('success', 'Розсилку опубліковано.');
    }
}
