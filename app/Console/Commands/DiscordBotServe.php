<?php

namespace App\Console\Commands;

use Discord\Discord;
use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;
use Discord\WebSockets\Intents;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Заготовка під майбутній Discord-бот — окремий проєкт, поки без
 * конкретних команд чи логіки. На відміну від Telegram (вебхук, живе
 * прямо всередині php-fpm запиту), DiscordPHP тримає постійне
 * WebSocket-зʼєднання (Gateway) через ReactPHP — тому це НЕ маршрут і
 * НЕ контролер, а окремий довгоживучий процес, який запускають так само,
 * як воркер черги (див. deploy/systemd/README.md — той самий підхід,
 * Restart=always у systemd-юніті).
 *
 * Навмисно НЕ підключено до setup-vps.sh: без токена команда сама
 * відмовляється стартувати, а вмикати завжди запущений (і завжди
 * падаючий без токена) сервіс під час КОЖНОГО деплою сайту — зайвий
 * ризик для того, що поки що не використовується. Коли токен зʼявиться,
 * додати systemd-юніт за прикладом laravel-worker.service (те саме
 * Restart=always, ExecStart=php artisan discord:bot).
 */
class DiscordBotServe extends Command
{
    protected $signature = 'discord:bot';

    protected $description = 'Запустити Discord-бота (постійний Gateway-процес, для майбутнього проєкту)';

    public function handle(): int
    {
        $token = config('services.discord.token');

        if (! $token) {
            $this->error('DISCORD_BOT_TOKEN не вказано в .env — нема з чим підключатись.');

            return self::FAILURE;
        }

        $discord = new Discord([
            'token' => $token,
            // MESSAGE_CONTENT — привілейований інтент: щоб бот бачив текст
            // повідомлень (а не лише факт їх появи), його треба явно
            // увімкнути в Discord Developer Portal для цього застосунку,
            // інакше Discord обірве зʼєднання при спробі його запросити.
            'intents' => Intents::getDefaultIntents(),
        ]);

        $discord->on('ready', function (Discord $discord) {
            Log::info('discord: бот на звʼязку', ['user' => (string) $discord->user?->username]);

            // Мінімальний доказ, що Gateway-зʼєднання реально працює —
            // сама логіка бота (команди, реакції) з'явиться разом із
            // проєктом, під який цей SDK і додано.
            $discord->on(Event::MESSAGE_CREATE, function (Message $message) {
                if ($message->author?->bot) {
                    return;
                }

                Log::debug('discord: повідомлення', [
                    'author' => (string) $message->author?->username,
                    'channel_id' => $message->channel_id,
                ]);
            });
        });

        $discord->run();

        return self::SUCCESS;
    }
}
