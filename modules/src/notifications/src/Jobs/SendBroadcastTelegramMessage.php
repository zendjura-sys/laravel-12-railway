<?php

namespace Addons\Notifications\Jobs;

use Addons\Notifications\Models\BroadcastDelivery;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

/**
 * Одна доставка = один отримувач. Ретраї — стандартний механізм черги
 * Laravel ($tries/$backoff), а не окрема система: throw з handle()
 * повертає job назад у чергу до вичерпання спроб, після чого викликається
 * failed() і статус фіксується як остаточно "failed".
 */
class SendBroadcastTelegramMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int,int> */
    public array $backoff = [60, 300, 900];

    public function __construct(public readonly int $deliveryId)
    {
    }

    public function handle(): void
    {
        $delivery = BroadcastDelivery::with('broadcast', 'user')->find($this->deliveryId);
        if (! $delivery || $delivery->status === 'sent') {
            return;
        }

        $delivery->increment('attempts');

        if (! class_exists(TelegramLink::class) || ! class_exists(TelegramClient::class)) {
            $delivery->update(['status' => 'failed', 'last_error' => 'TelegramBot не встановлено']);

            return;
        }

        $link = TelegramLink::query()->where('user_id', $delivery->user_id)->whereNotNull('linked_at')->first();
        if (! $link || ! $link->chat_id) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Telegram не прив\'язано']);

            return;
        }

        $client = new TelegramClient();
        if (! $client->isConfigured()) {
            $delivery->update(['status' => 'failed', 'last_error' => 'Bot Token не налаштовано']);

            return;
        }

        $broadcast = $delivery->broadcast;
        $text = '<b>'.e($broadcast->title).'</b>'."\n\n".e($broadcast->body);
        $messageId = $client->sendMessage((string) $link->chat_id, $text);

        if ($messageId === null) {
            throw new RuntimeException("Не вдалося надіслати broadcast #{$broadcast->id} користувачу {$delivery->user_id}");
        }

        $delivery->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        BroadcastDelivery::where('id', $this->deliveryId)->update([
            'status' => 'failed',
            'last_error' => $exception?->getMessage(),
        ]);
    }
}
