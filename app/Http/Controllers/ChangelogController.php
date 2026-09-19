<?php

namespace App\Http\Controllers;

use App\Models\ChangelogEntry;
use Inertia\Inertia;
use Inertia\Response;

class ChangelogController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Changelog/Index', [
            'entries' => ChangelogEntry::query()
                ->orderByDesc('published_at')
                ->orderByDesc('id')
                ->get(['id', 'title', 'description', 'published_at']),
        ]);
    }
}
