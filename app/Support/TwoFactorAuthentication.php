<?php

namespace App\Support;

use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * Тонка обгортка над pragmarx/google2fa — щоб і форма входу (виклик коду),
 * і налаштування в профілі користувались тим самим кодом перевірки, а не
 * двома трохи різними копіями.
 */
class TwoFactorAuthentication
{
    private Google2FA $engine;

    public function __construct()
    {
        $this->engine = new Google2FA();
    }

    public function generateSecretKey(): string
    {
        return $this->engine->generateSecretKey();
    }

    public function verify(string $secret, string $code): bool
    {
        // window=1 — приймає код за попередні/наступні 30 секунд: без
        // цього різниця годинника на телефоні на кілька секунд означала б
        // "невірний код" навіть із правильним застосунком-аутентифікатором.
        return $this->engine->verifyKey($secret, $code, 1);
    }

    /** SVG QR-код будується локально (bacon/bacon-qr-code) — секрет ніколи не йде на сторонній сервіс. */
    public function qrCodeSvg(User $user, string $secret): string
    {
        $qrUrl = $this->engine->getQRCodeUrl(
            config('app.name', 'Monsory Connect'),
            $user->email,
            $secret,
        );

        $renderer = new ImageRenderer(new RendererStyle(200), new SvgImageBackEnd());

        return (new Writer($renderer))->writeString($qrUrl);
    }

    /** @return array<int, string> */
    public function generateRecoveryCodes(int $count = 8): array
    {
        return collect()->times($count, fn () => Str::random(10).'-'.Str::random(10))->all();
    }
}
