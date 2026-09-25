<?php

namespace Addons\TelegramBot\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Чат щойно привʼязано до акаунту на сайті. Слухачі (наприклад,
 * Progression — ачівка "На звʼязку") підключаються через string-літерал
 * ::class у своєму events.php, тому цей модуль не знає й не мусить знати,
 * хто саме слухає.
 */
class AccountLinked
{
    use Dispatchable;

    public function __construct(public readonly int $userId)
    {
    }
}
