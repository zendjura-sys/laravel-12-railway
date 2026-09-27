<?php

namespace Addons\TelegramBot\Services;

use Addons\TelegramBot\Models\TelegramApplication;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

/**
 * Вступление в группу родины после одобрения заявки.
 *
 * ВАЖНО про «добавить автоматически»: Bot API не умеет добавлять людей в
 * чат — такого метода нет вовсе, это доступно только клиентскому API от
 * имени самого пользователя. Поэтому «автодобавление» здесь собрано из
 * двух разрешённых механизмов:
 *
 *  1. одноразовая ссылка с истечением, которая уходит человеку сразу
 *     после одобрения — вход в одно нажатие;
 *  2. авто-пропуск заявок на вступление (chat_join_request): если группа
 *     закрыта ссылкой с подтверждением, бот сам впускает тех, чья заявка
 *     одобрена, и не трогает остальных.
 *
 * Оба требуют, чтобы бот был администратором группы с правом приглашать.
 */
class FamilyGroup
{
    public function __construct(private readonly TelegramClient $telegram) {}

    /** ID группы из настроек, например -1004369425235. */
    public function id(): ?string
    {
        $id = trim((string) Setting::get('telegram_group_id'));

        return $id !== '' ? $id : null;
    }

    public function isConfigured(): bool
    {
        return $this->id() !== null && $this->telegram->isConfigured();
    }

    /**
     * Ссылка для конкретной заявки: уже выданная или новая.
     *
     * Переиспользуем сохранённую, потому что ссылка одноразовая — выдать
     * вторую значит оставить болтаться первую, всё ещё действующую.
     */
    public function inviteFor(TelegramApplication $application): ?string
    {
        if (! $this->isConfigured()) {
            return null;
        }

        if ($application->invite_link) {
            return $application->invite_link;
        }

        $result = $this->telegram->createInviteLink($this->id());

        if (! $result['ok'] || ! $result['link']) {
            Log::warning('telegram: не вдалося створити запрошення', [
                'application' => $application->id,
                'error' => $result['message'],
            ]);

            return null;
        }

        $application->update(['invite_link' => $result['link']]);

        return $result['link'];
    }

    /**
     * Человек нажал на ссылку с подтверждением. Впускаем, только если его
     * заявка одобрена: иначе группа открыта любому, кто нашёл ссылку.
     *
     * Заявку чужака НЕ отклоняем — оставляем администраторам: человек мог
     * прийти по приглашению участника, мимо анкеты, и автоматический отказ
     * был бы грубее, чем ожидание.
     */
    public function handleJoinRequest(string $chatId, int $userId): bool
    {
        if ($this->id() === null || $chatId !== $this->id()) {
            return false;
        }

        // В личке chat_id совпадает с user_id, поэтому заявку ищем по нему.
        $application = TelegramApplication::approvedFor((string) $userId);

        if (! $application) {
            return false;
        }

        if (! $this->telegram->approveJoinRequest($chatId, $userId)) {
            return false;
        }

        $application->update(['joined_at' => now()]);

        return true;
    }

    /** Отметить, что человек уже в группе, чтобы не предлагать вход снова. */
    public function refreshMembership(TelegramApplication $application): void
    {
        if (! $this->isConfigured() || $application->joined_at) {
            return;
        }

        $status = $this->telegram->chatMemberStatus($this->id(), (int) $application->chat_id);

        if (in_array($status, ['creator', 'administrator', 'member', 'restricted'], true)) {
            $application->update(['joined_at' => now()]);
        }
    }
}
