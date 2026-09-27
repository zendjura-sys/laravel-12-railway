<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UnionBlacklistedFamily;
use App\Models\UnionBlacklistedPlayer;
use App\Models\UnionComplaint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * ЧСС (чорний список союзу) з боку адмінки: родини блокує лише
 * union.manage (тимчасовий бан — з рахунком годин), гравців сюди
 * додає будь-який союзник (UnionBlacklistController, member-facing) —
 * тут адмін лише бачить список і може видалити запис, якщо ним зловжили.
 */
class UnionBlacklistController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Union/Blacklist', [
            'families' => UnionBlacklistedFamily::query()
                ->with('addedBy:id,name')
                ->latest()
                ->get(),
            'players' => UnionBlacklistedPlayer::query()
                ->with('addedBy:id,name')
                ->latest()
                ->get(),
            'reasons' => UnionComplaint::REASONS,
        ]);
    }

    public function storeFamily(Request $request): RedirectResponse
    {
        $data = $this->validatedFamily($request);

        UnionBlacklistedFamily::create([
            ...$data,
            'expires_at' => Carbon::now()->addHours($data['duration_hours']),
            'added_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Родину додано до ЧСС.');
    }

    public function updateFamily(Request $request, UnionBlacklistedFamily $unionBlacklistedFamily): RedirectResponse
    {
        $data = $this->validatedFamily($request);

        $unionBlacklistedFamily->update([
            ...$data,
            'expires_at' => Carbon::now()->addHours($data['duration_hours']),
        ]);

        return back()->with('status', 'Запис ЧСС оновлено.');
    }

    public function destroyFamily(UnionBlacklistedFamily $unionBlacklistedFamily): RedirectResponse
    {
        $unionBlacklistedFamily->delete();

        return back()->with('status', 'Родину прибрано з ЧСС.');
    }

    public function destroyPlayer(UnionBlacklistedPlayer $unionBlacklistedPlayer): RedirectResponse
    {
        $unionBlacklistedPlayer->delete();

        return back()->with('status', 'Гравця прибрано з ЧС.');
    }

    /** @return array{family_name:string,reason:string,duration_hours:int} */
    private function validatedFamily(Request $request): array
    {
        return $request->validate([
            'family_name' => ['required', 'string', 'max:150'],
            'reason' => ['required', 'string', 'max:1000'],
            // 1 година .. 9999 днів.
            'duration_hours' => ['required', 'integer', 'min:1', 'max:'.(9999 * 24)],
        ]);
    }
}
