<?php

namespace App\Http\Controllers;

use App\Models\UnionBlacklistedFamily;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Автодоповнення назви родини союзу — використовується у формі скарги
 * (проти якої родини) і у формі ЧС гравця (в якій родині він перебуває).
 * Джерело — union_family_name зареєстрованих союзників, а НЕ довільний
 * текст: інакше та сама родина осідала б у БД під п'ятьма написаннями.
 * Родини з активного ЧСС позначені окремо — форма попереджає про це.
 */
class UnionFamilyController extends Controller
{
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query('q', ''));

        if (mb_strlen($query) < 2) {
            return response()->json(['ok' => true, 'message' => null, 'data' => ['families' => []], 'errors' => null, 'redirect' => null]);
        }

        $registered = User::query()
            ->whereNotNull('union_family_name')
            ->where('union_family_name', 'like', '%'.$query.'%')
            ->distinct()
            ->orderBy('union_family_name')
            ->limit(10)
            ->pluck('union_family_name');

        $blacklisted = UnionBlacklistedFamily::query()
            ->active()
            ->where('family_name', 'like', '%'.$query.'%')
            ->distinct()
            ->pluck('family_name');

        $families = $registered->merge($blacklisted)->unique(fn (string $name) => mb_strtolower($name))->values();

        $result = $families->map(fn (string $name) => [
            'name' => $name,
            'blacklisted' => $blacklisted->contains(fn (string $b) => mb_strtolower($b) === mb_strtolower($name)),
        ]);

        return response()->json([
            'ok' => true,
            'message' => null,
            'data' => ['families' => $result],
            'errors' => null,
            'redirect' => null,
        ]);
    }
}
