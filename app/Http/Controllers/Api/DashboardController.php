<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\MemberCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Перший "справжній" екран мобільного застосунку — дзеркалить те саме
 * bankCard-збирання, що й /dashboard у routes/web.php (та сама опційна
 * залежність від Bonuses через class_exists), лише у форматі JSON.
 */
class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $bankCard = null;
        if (class_exists(\Addons\Bonuses\Services\BalanceCalculator::class)) {
            $bankCard = [
                'number' => MemberCard::masked($user),
                'numberFull' => MemberCard::number($user),
                'name' => $user->name,
                'balance' => \Addons\Bonuses\Services\BalanceCalculator::balanceFor($user->id),
            ];
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'position' => $user->position_title,
                'avatar_url' => $user->avatar_path ? \Illuminate\Support\Facades\Storage::url($user->avatar_path) : null,
            ],
            'bankCard' => $bankCard,
        ]);
    }
}
