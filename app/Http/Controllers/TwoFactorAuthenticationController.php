<?php

namespace App\Http\Controllers;

use App\Support\TwoFactorAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Налаштування 2FA в профілі — окремо від TwoFactorChallengeController
 * (той — про вхід уже залогіненим кодом, цей — про ввімкнути/вимкнути).
 */
class TwoFactorAuthenticationController extends Controller
{
    public function __construct(private readonly TwoFactorAuthentication $service)
    {
    }

    /**
     * Генерує НОВИЙ секрет і одразу перезаписує старий (незалежно, був він
     * підтверджений чи ні) — "Увімкнути" завжди починає з чистого аркуша,
     * а не намагається повторно показати QR для секрету, який міг уже
     * встигнути кудись протекти в попередній спробі.
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->forceFill([
            'two_factor_secret' => $this->service->generateSecretKey(),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function qrCode(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->two_factor_secret) {
            abort(404);
        }

        return response()->json(['svg' => $this->service->qrCodeSvg($user, $user->two_factor_secret)]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->service->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Невірний код. Перевірте час на телефоні й спробуйте ще раз.']);
        }

        $codes = $this->service->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();

        // Резервні коди показуємо ОДИН раз одразу тут — вдруге їх не
        // дістати (recovery_codes лежать у $hidden), тільки регенерувати.
        return back()->with('recoveryCodes', $codes);
    }

    /**
     * Скасувати НЕПІДТВЕРДЖЕНЕ налаштування — пароль тут не питаємо
     * навмисно: поки секрет не підтверджено кодом, він ще нічого не
     * захищає, це просто прибирання чернетки.
     */
    public function cancel(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasConfirmedTwoFactor()) {
            abort(409, '2FA вже підтверджена — скасувати нема чого, використайте "Вимкнути".');
        }

        $user->forceFill(['two_factor_secret' => null])->save();

        return back();
    }

    public function destroy(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $request->user()->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return back();
    }

    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);

        $user = $request->user();
        if (! $user->hasConfirmedTwoFactor()) {
            abort(404);
        }

        $codes = $this->service->generateRecoveryCodes();
        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return back()->with('recoveryCodes', $codes);
    }
}
