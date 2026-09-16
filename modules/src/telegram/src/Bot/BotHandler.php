<?php

namespace Addons\TelegramBot\Bot;

use Addons\TelegramBot\Models\TelegramApplication;
use Addons\TelegramBot\Models\TelegramChat;
use Addons\TelegramBot\Models\TelegramLink;
use Addons\TelegramBot\Services\TelegramClient;
use App\Models\User;

/**
 * Разбор входящих апдейтов и отрисовка экранов.
 *
 * Правило интерфейса: бот управляется ТОЛЬКО кнопками. Команд нет ни
 * одной, /start обрабатывается лишь потому, что Telegram отправляет её
 * сам при первом открытии чата и при переходе по deep-link — набирать её
 * руками не нужно. Любой другой текст, пришедший не в ответ на вопрос
 * анкеты, просто возвращает человека в меню, а не ругается на «неизвестную
 * команду».
 */
class BotHandler
{
    /** Шаги анкеты, на которых бот ждёт именно текст, а не нажатие. */
    private const TEXT_STEPS = ['nickname', 'about'];

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly Screens $screens,
    ) {}

    /** @param array<string,mixed> $update */
    public function handle(array $update): void
    {
        if (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);

            return;
        }

        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        }
    }

    /* ==================== ВХОДЯЩИЕ ==================== */

    /** @param array<string,mixed> $message */
    private function onMessage(array $message): void
    {
        $chatId = (string) ($message['chat']['id'] ?? '');
        if ($chatId === '') {
            return;
        }

        $chat = TelegramChat::forChat(
            $chatId,
            $message['from']['username'] ?? null,
            $message['from']['first_name'] ?? null,
        );

        $text = trim((string) ($message['text'] ?? ''));
        $messageId = $message['message_id'] ?? null;

        if (str_starts_with($text, '/start')) {
            // Deep-link с сайта: t.me/bot?start=КОД. Telegram присылает это
            // как /start КОД — человек ничего не набирал, он нажал кнопку.
            $payload = trim(substr($text, strlen('/start')));
            if ($payload !== '') {
                $this->linkByCode($chat, $payload);
            }

            $this->openFreshMenu($chat);

            return;
        }

        if (in_array($chat->step, self::TEXT_STEPS, true) && $text !== '') {
            // Ответ на вопрос анкеты убираем из чата: панель бота остаётся
            // единственным сообщением, а не тонет среди реплик.
            if ($messageId) {
                $this->telegram->deleteMessage($chatId, (int) $messageId);
            }
            $this->consumeText($chat, $text);

            return;
        }

        // Любой другой текст — не ошибка и не команда: просто показываем меню.
        $this->openFreshMenu($chat);
    }

    /** @param array<string,mixed> $callback */
    private function onCallback(array $callback): void
    {
        $chatId = (string) ($callback['message']['chat']['id'] ?? '');
        $callbackId = (string) ($callback['id'] ?? '');

        if ($chatId === '') {
            return;
        }

        // Снимаем «часики» до любой работы: пользователь видит реакцию
        // сразу, даже если дальше мы лезем в базу.
        if ($callbackId !== '') {
            $this->telegram->answerCallback($callbackId);
        }

        $chat = TelegramChat::forChat(
            $chatId,
            $callback['from']['username'] ?? null,
            $callback['from']['first_name'] ?? null,
        );

        // Нажали в этом сообщении — значит именно оно и есть текущая панель,
        // даже если раньше мы помнили другое (например, человек пролистал
        // историю и ткнул в старое меню).
        $messageId = $callback['message']['message_id'] ?? null;
        if ($messageId) {
            $chat->update(['menu_message_id' => (int) $messageId]);
        }

        $this->route($chat, (string) ($callback['data'] ?? ''));
    }

    /* ==================== МАРШРУТИЗАЦИЯ ==================== */

    private function route(TelegramChat $chat, string $data): void
    {
        [$action, $arg] = array_pad(explode(':', $data, 2), 2, null);

        $screen = match ($action) {
            'home' => $this->homeScreen($chat),
            'about' => $this->screens->about(),
            'directions' => $this->screens->directions(),
            'positions' => $this->screens->positions(),
            'structure' => $this->screens->structure(),
            'pos' => $this->screens->position((int) $arg),
            'growth' => $this->screens->growth(),
            'account' => $this->accountScreen($chat),
            'unlink' => $this->unlink($chat),
            'apply' => $this->apply($chat, $arg),
            default => $this->homeScreen($chat),
        };

        // Экраны вне анкеты сбрасывают незаконченный черновик: иначе
        // следующее случайное сообщение в чат попадёт в брошенную заявку.
        if ($action !== 'apply' && $chat->step !== null) {
            $chat->clearDraft();
        }

        $this->render($chat, $screen);
    }

    /** @return array{text:string,keyboard:array<string,mixed>} */
    private function apply(TelegramChat $chat, ?string $arg): array
    {
        [$key, $value] = array_pad(explode(':', (string) $arg, 2), 2, null);

        return match ($key) {
            null, '' => $this->screens->applyIntro(TelegramApplication::pendingFor($chat->chat_id)),
            'start' => $this->startApplication($chat),
            'age' => $this->answer($chat, 'age_range', Screens::AGES, $value, 'playtime'),
            'time' => $this->answer($chat, 'playtime', Screens::PLAYTIME, $value, 'experience'),
            'exp' => $this->answer($chat, 'experience', Screens::EXPERIENCE, $value, 'direction'),
            'dir' => $this->answer($chat, 'direction', Screens::DIRECTIONS, $value, 'about'),
            'skip' => $this->skipAbout($chat),
            'send' => $this->submit($chat),
            'cancel' => $this->cancel($chat),
            default => $this->screens->applyIntro(TelegramApplication::pendingFor($chat->chat_id)),
        };
    }

    /* ==================== АНКЕТА ==================== */

    private function startApplication(TelegramChat $chat): array
    {
        if ($pending = TelegramApplication::pendingFor($chat->chat_id)) {
            return $this->screens->applyIntro($pending);
        }

        $chat->update(['step' => 'nickname', 'draft' => []]);

        return $this->screens->askNickname();
    }

    /**
     * Общий обработчик кнопочного ответа: пишет выбранный вариант в
     * черновик и переводит на следующий шаг.
     *
     * @param  array<int,string>  $options
     */
    private function answer(TelegramChat $chat, string $field, array $options, ?string $index, string $nextStep): array
    {
        // Кнопку могли нажать в старом сообщении, когда анкета уже
        // отменена или отправлена — тогда возвращаем на экран заявки,
        // а не пишем в пустой черновик.
        if ($chat->step === null) {
            return $this->screens->applyIntro(TelegramApplication::pendingFor($chat->chat_id));
        }

        $i = (int) $index;
        if (! array_key_exists($i, $options)) {
            return $this->screens->oops();
        }

        $chat->putDraft($field, $options[$i]);
        $chat->update(['step' => $nextStep]);

        return $this->screenForStep($nextStep);
    }

    private function skipAbout(TelegramChat $chat): array
    {
        if ($chat->step === null) {
            return $this->screens->applyIntro(TelegramApplication::pendingFor($chat->chat_id));
        }

        $chat->putDraft('about', null);
        $chat->update(['step' => 'confirm']);

        return $this->screens->confirm($chat->fresh()->draftData());
    }

    /** Текстовые шаги: ник и «про себе». */
    private function consumeText(TelegramChat $chat, string $text): void
    {
        $value = mb_substr($text, 0, $chat->step === 'nickname' ? 32 : 500);

        if ($chat->step === 'nickname') {
            $chat->putDraft('nickname', $value);
            $chat->update(['step' => 'age']);
            $this->render($chat, $this->screens->askAge());

            return;
        }

        $chat->putDraft('about', $value);
        $chat->update(['step' => 'confirm']);
        $this->render($chat, $this->screens->confirm($chat->fresh()->draftData()));
    }

    private function submit(TelegramChat $chat): array
    {
        $draft = $chat->draftData();

        // Анкету могли открыть в старом сообщении и нажать «Надіслати»
        // повторно — без этой проверки в базу летит заявка с пустым ником.
        if (($draft['nickname'] ?? '') === '') {
            $chat->clearDraft();

            return $this->screens->applyIntro(TelegramApplication::pendingFor($chat->chat_id));
        }

        if ($pending = TelegramApplication::pendingFor($chat->chat_id)) {
            $chat->clearDraft();

            return $this->screens->applyIntro($pending);
        }

        TelegramApplication::create([
            'chat_id' => $chat->chat_id,
            'telegram_username' => $chat->telegram_username,
            'user_id' => $this->linkedUser($chat)?->id,
            'nickname' => $draft['nickname'],
            'age_range' => $draft['age_range'] ?? '—',
            'playtime' => $draft['playtime'] ?? '—',
            'experience' => $draft['experience'] ?? '—',
            'direction' => $draft['direction'] ?? '—',
            'about' => $draft['about'] ?? null,
            'status' => TelegramApplication::STATUS_PENDING,
        ]);

        $chat->clearDraft();

        return $this->screens->submitted();
    }

    private function cancel(TelegramChat $chat): array
    {
        $chat->clearDraft();

        TelegramApplication::query()
            ->where('chat_id', $chat->chat_id)
            ->where('status', TelegramApplication::STATUS_PENDING)
            ->update([
                'status' => TelegramApplication::STATUS_REJECTED,
                'review_note' => 'Скасовано автором',
                'reviewed_at' => now(),
            ]);

        return $this->screens->cancelled();
    }

    private function screenForStep(string $step): array
    {
        return match ($step) {
            'nickname' => $this->screens->askNickname(),
            'age' => $this->screens->askAge(),
            'playtime' => $this->screens->askPlaytime(),
            'experience' => $this->screens->askExperience(),
            'direction' => $this->screens->askDirection(),
            'about' => $this->screens->askAbout(),
            default => $this->screens->oops(),
        };
    }

    /* ==================== АККАУНТ ==================== */

    private function linkedUser(TelegramChat $chat): ?User
    {
        return TelegramLink::query()
            ->where('chat_id', $chat->chat_id)
            ->whereNotNull('linked_at')
            ->first()?->user;
    }

    private function homeScreen(TelegramChat $chat): array
    {
        return $this->screens->home($chat, $this->linkedUser($chat)?->name);
    }

    private function accountScreen(TelegramChat $chat): array
    {
        $user = $this->linkedUser($chat);

        return $this->screens->account($user?->name, $user?->email);
    }

    private function unlink(TelegramChat $chat): array
    {
        TelegramLink::query()
            ->where('chat_id', $chat->chat_id)
            ->update(['chat_id' => null, 'linked_at' => null, 'link_code' => null]);

        return $this->screens->account(null, null);
    }

    private function linkByCode(TelegramChat $chat, string $code): void
    {
        $link = TelegramLink::findByValidCode($code);
        if (! $link) {
            return;
        }

        // Один Telegram-чат — один аккаунт: старую привязку этого же чата
        // к другому пользователю снимаем.
        TelegramLink::query()
            ->where('chat_id', $chat->chat_id)
            ->where('id', '!=', $link->id)
            ->update(['chat_id' => null, 'linked_at' => null]);

        $link->update([
            'chat_id' => $chat->chat_id,
            'telegram_username' => $chat->telegram_username,
            'linked_at' => now(),
            'link_code' => null,
        ]);
    }

    /* ==================== ОТРИСОВКА ==================== */

    /**
     * Первое открытие чата: старую панель убираем, чтобы в истории не
     * осталось меню с живыми кнопками выше нового.
     */
    private function openFreshMenu(TelegramChat $chat): void
    {
        if ($chat->menu_message_id) {
            $this->telegram->deleteMessage($chat->chat_id, $chat->menu_message_id);
        }

        $screen = $this->homeScreen($chat);
        $messageId = $this->telegram->sendMessage($chat->chat_id, $screen['text'], $screen['keyboard']);
        $chat->update(['menu_message_id' => $messageId]);
    }

    /** @param array{text:string,keyboard:array<string,mixed>} $screen */
    private function render(TelegramChat $chat, array $screen): void
    {
        if ($chat->menu_message_id) {
            $edited = $this->telegram->editMessage(
                $chat->chat_id,
                $chat->menu_message_id,
                $screen['text'],
                $screen['keyboard'],
            );

            if ($edited) {
                return;
            }
        }

        // Сообщение могли удалить вручную или оно старше 48 часов — тогда
        // редактировать нечего, отправляем новое и запоминаем его.
        $messageId = $this->telegram->sendMessage($chat->chat_id, $screen['text'], $screen['keyboard']);
        $chat->update(['menu_message_id' => $messageId]);
    }
}
