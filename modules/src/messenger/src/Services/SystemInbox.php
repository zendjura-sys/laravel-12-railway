<?php

namespace Addons\Messenger\Services;

use Addons\Messenger\Models\Conversation;
use Addons\Messenger\Models\ConversationParticipant;
use Addons\Messenger\Models\Message;
use App\Models\User;

/**
 * Службовий чат "Monsory Finance" — у кожного учасника свій, read-only:
 * conversations.type = 'finance', єдиний учасник — сам користувач,
 * повідомлення без відправника (sender_id NULL). Інші модулі (Bonuses)
 * пишуть сюди через class_exists() — без жорсткої залежності від месенджера.
 *
 * Push тут навмисно не шлеться: той, хто викликає, зазвичай і так шле
 * особисте сповіщення (NotificationService) з посиланням на цей чат —
 * інакше на телефон приходило б два однакові повідомлення.
 */
class SystemInbox
{
    public const FINANCE_TITLE = 'Monsory Finance';

    public function postFinance(User $user, string $text): Conversation
    {
        $conversation = $this->financeConversationFor($user);

        Message::create([
            'conversation_id' => $conversation->id,
            'sender_id' => null,
            'body' => $text,
            'type' => 'text',
        ]);
        $conversation->touch();

        return $conversation;
    }

    public function financeConversationFor(User $user): Conversation
    {
        $existingId = ConversationParticipant::query()
            ->where('user_id', $user->id)
            ->whereHas('conversation', fn ($q) => $q->where('type', 'finance'))
            ->value('conversation_id');

        if ($existingId) {
            return Conversation::query()->findOrFail($existingId);
        }

        $conversation = Conversation::create(['type' => 'finance']);
        ConversationParticipant::create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);

        return $conversation;
    }
}
