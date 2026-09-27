<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;

class VerifyEmailController extends Controller
{
    /**
     * Mark the authenticated user's email address as verified.
     */
    public function __invoke(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
        }

        if ($request->user()->markEmailAsVerified()) {
            event(new Verified($request->user()));
        }

        // Свіжопідтверджений email — саме той момент, коли варто одразу
        // запропонувати прив'язати Telegram, а не лишати людину саму
        // здогадуватись, що це є в профілі. Повторне підтвердження (гілка
        // вище) на дашборд, без повтору цієї пропозиції щоразу.
        return redirect()->intended(route('profile.edit', absolute: false).'?link_telegram=1');
    }
}
