<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\TwoFactorAuthentication;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Токен-автентифікація для мобільного застосунку (Flutter). Окремо від
 * AuthenticatedSessionController (веб-сесія) — тут немає кукі й CSRF,
 * лише Sanctum personal access token на пристрій. Дзеркалить ту саму
 * 2FA-логіку двома кроками, що й веб (TwoFactorChallengeController), але
 * замість проміжного стану в сесії — короткоживучий challenge-токен у
 * кеші, бо між кроком 1 і 2 застосунок не тримає сесії сервера.
 */
class AuthController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $twoFactor)
    {
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $throttleKey = Str::transliterate(Str::lower($data['email'])).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            event(new Lockout($request));
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', ['seconds' => $seconds, 'minutes' => ceil($seconds / 60)]),
            ]);
        }

        if (! Auth::guard('web')->validate(['email' => $data['email'], 'password' => $data['password']])) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages(['email' => trans('auth.failed')]);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = User::where('email', $data['email'])->firstOrFail();

        if ($user->hasConfirmedTwoFactor()) {
            $challenge = Str::random(64);
            Cache::put('mobile-2fa:'.$challenge, $user->id, now()->addMinutes(5));

            return response()->json([
                'requires_two_factor' => true,
                'challenge_token' => $challenge,
            ]);
        }

        return response()->json($this->issueToken($user, $data['device_name']));
    }

    public function loginTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['nullable', 'string'],
            'recovery_code' => ['nullable', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
        ]);

        $userId = Cache::get('mobile-2fa:'.$data['challenge_token']);
        if (! $userId) {
            throw ValidationException::withMessages(['challenge_token' => 'Код підтвердження прострочено, увійдіть ще раз.']);
        }

        $user = User::find($userId);
        if (! $user) {
            throw ValidationException::withMessages(['challenge_token' => 'Код підтвердження прострочено, увійдіть ще раз.']);
        }

        $verified = $this->verifyCode($user, $data) || $this->verifyRecoveryCode($user, $data);

        if (! $verified) {
            throw ValidationException::withMessages(['code' => 'Невірний код підтвердження.']);
        }

        Cache::forget('mobile-2fa:'.$data['challenge_token']);

        return response()->json($this->issueToken($user, $data['device_name']));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Вихід виконано.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position_title,
            'avatar_url' => $user->avatar_path ? \Illuminate\Support\Facades\Storage::url($user->avatar_path) : null,
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ]);
    }

    /** @return array{token: string, user: array<string, mixed>} */
    private function issueToken(User $user, string $deviceName): array
    {
        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'position' => $user->position_title,
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
        ];
    }

    /** @param array<string, mixed> $data */
    private function verifyCode(User $user, array $data): bool
    {
        if (empty($data['code']) || ! $user->two_factor_secret) {
            return false;
        }

        return $this->twoFactor->verify($user->two_factor_secret, $data['code']);
    }

    /** @param array<string, mixed> $data */
    private function verifyRecoveryCode(User $user, array $data): bool
    {
        if (empty($data['recovery_code'])) {
            return false;
        }

        $codes = $user->two_factor_recovery_codes ?? [];
        $index = array_search($data['recovery_code'], $codes, true);

        if ($index === false) {
            return false;
        }

        unset($codes[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

        return true;
    }
}
