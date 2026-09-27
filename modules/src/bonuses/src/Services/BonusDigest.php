<?php

namespace Addons\Bonuses\Services;

use Addons\Bonuses\Models\BonusPayout;
use Addons\Bonuses\Models\BonusSettings;
use Addons\TelegramBot\Services\FamilyGroup;
use Addons\TelegramBot\Services\TelegramClient;
use Addons\TelegramBot\Support\MessageFormat;
use Illuminate\Support\Carbon;

/**
 * Список нарахувань за щойно порахований тиждень в сімейний Telegram-чат —
 * одне повідомлення, а не N особистих (це не персональне сповіщення,
 * а публічна відомість, як і задумано брифом).
 */
class BonusDigest
{
    public function sendFor(Carbon $weekStart): void
    {
        if (! class_exists(FamilyGroup::class) || ! class_exists(TelegramClient::class)) {
            return;
        }

        $settings = BonusSettings::current();

        $payouts = BonusPayout::query()
            ->with('user:id,name')
            ->where('week_start', $weekStart->toDateString())
            ->where('total_amount', '>', $settings->min_digest_amount)
            ->orderByDesc('total_amount')
            ->get();

        if ($payouts->isEmpty()) {
            return;
        }

        $client = new TelegramClient();
        $familyGroup = new FamilyGroup($client);
        if (! $familyGroup->isConfigured()) {
            return;
        }

        $lines = $payouts->map(fn (BonusPayout $p) => e($p->user->name).' — <b>'.number_format($p->total_amount, 0, ',', ' ').'₴</b>');

        $client->sendMessage($familyGroup->id(), MessageFormat::card('💰', 'Премії за тиждень', $lines->implode("\n")));
    }
}
