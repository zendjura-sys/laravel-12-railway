<?php

namespace App\Http\Controllers;

use App\Support\GuideContent;
use Inertia\Inertia;
use Inertia\Response;

class GuideController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Guide/Index', [
            'categories' => GuideContent::categories(),
        ]);
    }
}
