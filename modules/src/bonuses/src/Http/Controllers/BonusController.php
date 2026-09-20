<?php

namespace Addons\Bonuses\Http\Controllers;

use Addons\Bonuses\Models\BankTransfer;
use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\InvestmentAchievementTier;
use Addons\Bonuses\Models\ManualBonusAward;
use Addons\Bonuses\Models\UserInvestmentAchievement;
use Addons\Bonuses\Services\BalanceCalculator;
use Addons\Reports\Models\Report;
use App\Models\User;
use App\Support\MemberCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BonusController
{
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $payouts = BonusPayout::query()
            ->where('user_id', $userId)
            ->orderByDesc('week_start')
            ->paginate(15);

        $cumulativeInvestment = (int) Report::query()
            ->where('user_id', $userId)->where('status', 'approved')->where('type', 'investment')
            ->sum('amount');

        $earnedTierIds = UserInvestmentAchievement::query()->where('user_id', $userId)->pluck('tier_id');

        $tiers = InvestmentAchievementTier::query()
            ->orderBy('sort_order')->orderBy('threshold_amount')
            ->get()
            ->map(fn (InvestmentAchievementTier $tier) => [
                'id' => $tier->id,
                'label' => $tier->label,
                'threshold_amount' => $tier->threshold_amount,
                'bonus_amount' => $tier->bonus_amount,
                'earned' => $earnedTierIds->contains($tier->id),
            ]);

        $manualAwards = ManualBonusAward::query()
            ->where('user_id', $userId)
            ->latest()
            ->get();

        $transfers = BankTransfer::query()
            ->where('from_user_id', $userId)->orWhere('to_user_id', $userId)
            ->with(['sender:id,name', 'recipient:id,name'])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (BankTransfer $t) => [
                'id' => $t->id,
                'direction' => $t->from_user_id === $userId ? 'out' : 'in',
                'counterparty' => $t->from_user_id === $userId ? $t->recipient?->name : $t->sender?->name,
                'amount' => $t->amount,
                'note' => $t->note,
                'created_at' => $t->created_at,
            ]);

        return Inertia::render('Bonuses/Index', [
            'payouts' => $payouts,
            'cumulativeInvestment' => $cumulativeInvestment,
            'tiers' => $tiers,
            'manualAwards' => $manualAwards,
            'transfers' => $transfers,
            'card' => [
                'number' => MemberCard::masked($request->user()),
                'numberFull' => MemberCard::number($request->user()),
                'name' => $request->user()->name,
                'balance' => BalanceCalculator::balanceFor($userId),
            ],
        ]);
    }

    /** Пошук отримувача переказу — як і скрізь, без тіньових акаунтів і без себе самого. */
    public function searchRecipients(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));
        if (mb_strlen($query) < 2) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['members' => []], 'errors' => null, 'redirect' => null]);
        }

        $members = User::query()
            ->where('id', '!=', $request->user()->id)
            ->where('is_shadow', false)
            ->where('name', 'like', '%'.$query.'%')
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['members' => $members],
            'errors' => null,
            'redirect' => null,
        ]);
    }

    public function storeTransfer(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'recipient_id' => ['required', 'integer', 'exists:users,id'],
            'amount' => ['required', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $sender = $request->user();

        if ((int) $data['recipient_id'] === $sender->id) {
            throw ValidationException::withMessages(['recipient_id' => 'Не можна переказати самому собі.']);
        }

        DB::transaction(function () use ($data, $sender) {
            // Баланс звіряємо ЗНОВУ тут, усередині транзакції — те, що
            // показала форма мить тому, могло вже застаріти (ще один
            // переказ у тому ж вікні).
            $balance = BalanceCalculator::balanceFor($sender->id);

            if ($data['amount'] > $balance) {
                throw ValidationException::withMessages(['amount' => 'Недостатньо коштів на балансі.']);
            }

            BankTransfer::create([
                'from_user_id' => $sender->id,
                'to_user_id' => $data['recipient_id'],
                'amount' => $data['amount'],
                'note' => $data['note'] ?? null,
            ]);
        });

        return back()->with('success', 'Переказ виконано.');
    }
}
