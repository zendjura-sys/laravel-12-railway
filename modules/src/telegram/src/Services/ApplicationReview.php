<?php

namespace Addons\TelegramBot\Services;

use Addons\AiAssistant\Services\ApplicationQualityReviewer;
use Addons\TelegramBot\Models\TelegramApplication;
use Addons\TelegramBot\Models\TelegramLink;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * Решение по заявке — в одном месте для всех, кто его принимает.
 *
 * Решение можно принять из админки и прямо из чата бота. Логика у них
 * одна: сменить статус, ответить человеку в тот же чат, из которого
 * заявка пришла, и при одобрении выдать персональную ссылку в группу.
 * Держать её в двух копиях означало бы, что рано или поздно одна отстанет
 * и, например, перестанет присылать приглашение.
 */
class ApplicationReview
{
    public const PERMISSION = 'telegram.manage';

    public function __construct(
        private readonly TelegramClient $telegram,
        private readonly FamilyGroup $group,
    ) {}

    /**
     * @return array{ok:bool,message:string}
     */
    public function decide(TelegramApplication $application, User $reviewer, bool $approve, ?string $note = null): array
    {
        if (! $reviewer->can(self::PERMISSION)) {
            return ['ok' => false, 'message' => 'Немає прав розглядати заявки.'];
        }

        // Решение могли уже принять — из панели или из чата другим
        // администратором. Вторым нажатием статус не переписываем.
        if (! $application->isPending()) {
            return ['ok' => false, 'message' => 'Цю заявку вже розглянули.'];
        }

        $application->update([
            'status' => $approve ? TelegramApplication::STATUS_APPROVED : TelegramApplication::STATUS_REJECTED,
            'review_note' => $note,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $application = $application->fresh();

        [$text, $keyboard, $inviteNote] = $this->messageFor($application, $approve, $note);

        $delivered = $this->telegram->sendMessage($application->chat_id, $text, $keyboard) !== null;

        $this->clearReviewerNotifications($application, $reviewer, $approve);

        return [
            'ok' => true,
            'message' => ($approve ? 'Заявку схвалено.' : 'Заявку відхилено.')
                .($delivered ? ' Повідомлення надіслано.' : ' Повідомлення в Telegram надіслати не вдалося.')
                .$inviteNote,
        ];
    }

    /**
     * Прибирає кнопки ✅/✖️ з УСІХ надісланих копій сповіщення "НОВА
     * ЗАЯВКА", а не лише з тієї, по якій хтось натиснув. Без цього
     * рішення, прийняте одним адміном (чи то з панелі сайту, чи з чату),
     * лишало кнопки живими в чатах усіх інших — повторне натискання ні
     * до чого не приводило б (isPending вище це ловить), але виглядало б
     * як заявка, яку ще ніхто не розглянув.
     */
    private function clearReviewerNotifications(TelegramApplication $application, User $reviewer, bool $approve): void
    {
        $notified = $application->notified_messages ?? [];
        if ($notified === []) {
            return;
        }

        $status = $approve ? '✅  схвалено' : '✖️  відхилено';
        $text = $this->summaryText($application)
            ."\n<b>{$status}</b>\n<i>".e($reviewer->name).'</i>';

        foreach ($notified as $entry) {
            $chatId = $entry['chat_id'] ?? null;
            $messageId = $entry['message_id'] ?? null;
            if ($chatId === null || $messageId === null) {
                continue;
            }

            $this->telegram->editMessage((string) $chatId, (int) $messageId, $text, ['inline_keyboard' => []]);
        }
    }

    /**
     * Сообщить администраторам о новой заявке прямо в чат, с кнопками
     * решения. Без этого заявка лежит незамеченной, пока кто-нибудь не
     * зайдёт в панель — а человек в это время ждёт.
     */
    public function notifyReviewers(TelegramApplication $application): int
    {
        $text = $this->summaryText($application).$this->aiQualityLine($application);

        $rows = [[
            ['text' => '✅  Схвалити', 'callback_data' => 'rev:a:'.$application->id],
            ['text' => '✖️  Відхилити', 'callback_data' => 'rev:r:'.$application->id],
        ]];

        if (Route::has('admin.telegram.index')) {
            $rows[] = [['text' => '📋  Список заявок', 'url' => route('admin.telegram.index').'#applications']];
        }

        $keyboard = ['inline_keyboard' => $rows];

        $notified = [];
        foreach ($this->reviewerChats($application->chat_id) as $chatId) {
            $messageId = $this->telegram->sendMessage($chatId, $text, $keyboard);
            if ($messageId !== null) {
                $notified[] = ['chat_id' => $chatId, 'message_id' => $messageId];
            }
        }

        // Запам'ятовуємо, кому саме пішло сповіщення — без цього після
        // рішення нема чим редагувати чужі копії, тільки ту, по якій
        // хтось особисто натиснув (див. clearReviewerNotifications).
        $application->update(['notified_messages' => $notified]);

        return count($notified);
    }

    /**
     * AI-assistant — опційна залежність (class_exists), і навіть коли
     * встановлено, перевіряється окремий тумблер: адмін міг вимкнути саме
     * цей напрямок використання, лишивши інші (аналіз фото, розсилки) увімкненими.
     */
    private function aiQualityLine(TelegramApplication $application): string
    {
        if (
            ! class_exists(ApplicationQualityReviewer::class)
            || Setting::get('ai_applications_review_enabled') !== '1'
        ) {
            return '';
        }

        $note = app(ApplicationQualityReviewer::class)->review($application);

        return $note ? "\n<i>🤖 ".e($note)."</i>\n" : '';
    }

    /** Той самий блок деталей — і в первинному сповіщенні, і в рішенні по ньому. */
    private function summaryText(TelegramApplication $application): string
    {
        $text = "◆  <b>НОВА ЗАЯВКА</b>\n━━━━━━━━━━━━━━━\n\n"
            .'<b>Нік:</b> '.e($application->nickname)."\n"
            .'<b>Вік:</b> '.e($application->age_range)."\n"
            .'<b>Час у грі:</b> '.e($application->playtime)."\n"
            .'<b>Досвід:</b> '.e($application->experience)."\n"
            .'<b>Напрямок:</b> '.e($application->direction)."\n";

        if ($application->telegram_username) {
            $text .= '<b>Telegram:</b> @'.e($application->telegram_username)."\n";
        }

        $about = trim((string) $application->about);
        if ($about !== '') {
            $text .= "\n<i>".e($about)."</i>\n";
        }

        return $text;
    }

    /**
     * Чаты администраторов, привязавших Telegram.
     *
     * Свой собственный чат исключаем: если заявку подал человек, у
     * которого есть права, уведомление о ней самому себе выглядит
     * поломкой.
     *
     * @return array<int,string>
     */
    private function reviewerChats(string $exceptChatId): array
    {
        return TelegramLink::query()
            ->whereNotNull('linked_at')
            ->where('chat_id', '!=', $exceptChatId)
            ->with('user')
            ->get()
            ->filter(fn (TelegramLink $link) => $link->user?->can(self::PERMISSION))
            ->pluck('chat_id')
            ->all();
    }

    /**
     * @return array{0:string,1:?array<string,mixed>,2:string}
     */
    private function messageFor(TelegramApplication $application, bool $approve, ?string $note): array
    {
        $text = $approve
            ? "◆  <b>ЗАЯВКУ СХВАЛЕНО</b>\n━━━━━━━━━━━━━━━\n\nВітаємо в Monsory Family, <b>".e($application->nickname)."</b>.\n\nЗ вами звʼяжеться керівництво щодо наступних кроків."
            : "◆  <b>ЗАЯВКУ ВІДХИЛЕНО</b>\n━━━━━━━━━━━━━━━\n\nДякуємо за інтерес до Monsory Family.\nЦього разу не склалося — подати нову заявку можна будь-коли.";

        if (trim((string) $note) !== '') {
            $text .= "\n\n<b>Коментар:</b> ".e(trim($note));
        }

        if (! $approve) {
            return [$text, null, ''];
        }

        // Одобрили — сразу выдаём персональную ссылку в группу. Добавить
        // человека самим нельзя: в Bot API нет такого метода, это умеет
        // только клиентский API от имени самого пользователя. Вход в одно
        // нажатие — максимум возможного.
        if (! $this->group->isConfigured()) {
            return [$text, null, ' Група не вказана в налаштуваннях — посилання не надіслано.'];
        }

        if ($link = $this->group->inviteFor($application)) {
            $text .= "\n\nЛишився один крок — увійдіть до групи родини.";

            return [$text, ['inline_keyboard' => [[['text' => '🚪  Увійти до групи', 'url' => $link]]]], ''];
        }

        return [$text, null, ' Посилання створити не вдалося — перевірте, що бот є адміністратором групи з правом запрошувати.'];
    }
}
