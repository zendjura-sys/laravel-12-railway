<?php

namespace App\Http\Controllers;

use App\Support\FamilyRules;
use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Inertia\Response;

class RulesController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rules/Index', FamilyRules::payload());
    }

    /** Той самий вміст для мобільного застосунку. */
    public function json(): JsonResponse
    {
        return response()->json(FamilyRules::payload());
    }
}
