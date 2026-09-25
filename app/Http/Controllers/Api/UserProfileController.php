<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Presence;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

/**
 * Публічний профіль учасника (свій чи чужий) — з месенджера, списку
 * учасників, галереї тощо. Тіньові акаунти (is_shadow) сюди не мають
 * потрапляти в жодному з місць, звідки викликається цей ендпоінт —
 * тут не додаткова перевірка, а той самий принцип, що й у
 * searchMembers() месенджера.
 */
class UserProfileController extends Controller
{
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'position' => $user->position_title,
            'avatarUrl' => $user->avatar_path ? Storage::url($user->avatar_path) : null,
            'memberSince' => $user->created_at,
            'birthDate' => $user->birth_date?->format('Y-m-d'),
            'presence' => Presence::payload($user),
        ]);
    }
}
