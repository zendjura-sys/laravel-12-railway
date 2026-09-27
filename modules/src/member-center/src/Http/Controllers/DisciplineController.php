<?php

namespace Addons\MemberCenter\Http\Controllers;

use Addons\MemberCenter\Models\MemberWarning;
use Addons\MemberCenter\Services\DisciplineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** «Мої покарання» — учасник бачить свій стан і сплачує штрафи з рахунку. */
class DisciplineController
{
    public function __construct(private readonly DisciplineService $discipline)
    {
    }

    public function index(Request $request): Response
    {
        return Inertia::render('Discipline/Index', $this->payload($request));
    }

    public function indexJson(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'message' => null, 'data' => $this->payload($request), 'errors' => null, 'redirect' => null]);
    }

    public function pay(Request $request, MemberWarning $warning): JsonResponse
    {
        $warning = $this->discipline->payFromBank($warning, $request->user());

        return response()->json([
            'ok' => true,
            'message' => 'Штраф '.DisciplineService::money($warning->amount).' сплачено з рахунку.',
            'data' => ['penalty' => $warning->toPayload()],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    private function payload(Request $request): array
    {
        $user = $request->user();

        return [
            'summary' => $this->discipline->summary($user),
            'bankAvailable' => DisciplineService::bankAvailable(),
            'penalties' => MemberWarning::query()
                ->with('author:id,name', 'resolver:id,name')
                ->where('user_id', $user->id)
                ->latest('id')
                ->limit(100)
                ->get()
                ->map(fn (MemberWarning $w) => $w->toPayload())
                ->values(),
        ];
    }
}
