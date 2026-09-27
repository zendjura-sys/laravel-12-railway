<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Онлайн-статус учасника без вебсокетів: users.last_seen_at оновлює
 * middleware TrackLastSeen на кожен автентифікований запит (сайт і
 * мобільний API), а відкритий сайт/застосунок раз на ~45с шле
 * heartbeat (POST /presence/ping). "У мережі" — був активний за
 * останні ONLINE_WINDOW секунд; закрив застосунок — за ~2 хв стає 🔴.
 */
final class Presence
{
    public const ONLINE_WINDOW = 120;

    /** Запис у БД не частіше, ніж раз на стільки секунд — polling чату йде кожні 3с. */
    public const TOUCH_THROTTLE = 30;

    public const DISPLAY_TZ = 'Europe/Kyiv';

    public static function touch(User $user): void
    {
        $lastSeen = $user->last_seen_at;
        if ($lastSeen && $lastSeen->gt(now()->subSeconds(self::TOUCH_THROTTLE))) {
            return;
        }

        // Напряму query builder-ом: без updated_at і без подій моделі —
        // це службова мітка, а не редагування профілю.
        User::query()->whereKey($user->id)->update(['last_seen_at' => now()]);
        $user->last_seen_at = now();
    }

    public static function isOnline(?User $user): bool
    {
        return (bool) $user?->last_seen_at?->gt(now()->subSeconds(self::ONLINE_WINDOW));
    }

    /** @return array{online: bool, emoji: string, lastSeenAt: ?string, label: string} */
    public static function payload(?User $user): array
    {
        $online = self::isOnline($user);

        return [
            'online' => $online,
            'emoji' => $online ? '🟢' : '🔴',
            'lastSeenAt' => $user?->last_seen_at?->toIso8601String(),
            'label' => self::label($user),
        ];
    }

    public static function label(?User $user): string
    {
        if (self::isOnline($user)) {
            return 'у мережі';
        }

        $seen = $user?->last_seen_at;
        if (! $user || ! $seen) {
            return 'не в мережі';
        }

        $was = $user->verb('був', 'була');
        $minutes = (int) floor($seen->diffInSeconds(now()) / 60);

        if ($minutes < 60) {
            return "{$was} у мережі ".max(1, $minutes).' хв тому';
        }

        $local = $seen->copy()->setTimezone(self::DISPLAY_TZ);
        $today = now(self::DISPLAY_TZ)->startOfDay();

        return match (true) {
            $local->gte($today) => "{$was} у мережі сьогодні о ".$local->format('H:i'),
            $local->gte($today->copy()->subDay()) => "{$was} у мережі вчора о ".$local->format('H:i'),
            $local->year === $today->year => "{$was} у мережі ".$local->format('d.m').' о '.$local->format('H:i'),
            default => "{$was} у мережі ".$local->format('d.m.Y'),
        };
    }

    /** @return Collection<int, int> */
    public static function onlineUserIds(): Collection
    {
        return User::query()
            ->where('is_shadow', false)
            ->where('last_seen_at', '>=', now()->subSeconds(self::ONLINE_WINDOW))
            ->pluck('id');
    }
}
