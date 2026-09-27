<?php

namespace Addons\Notifications\Listeners;

use Addons\MemberCenter\Models\MemberWarning;
use Addons\Notifications\Services\NotificationService;
use App\Models\User;

/** Сплата, скасування, прострочка штрафу й згоряння покарання. */
class NotifyOnMemberPenaltyUpdated
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    public function handle($event): void
    {
        $w = $event->warning;
        $user = User::find($w->user_id);
        if (! $user) {
            return;
        }

        $label = MemberWarning::TYPE_LABELS[$w->type] ?? 'Покарання';
        $money = fn (?int $a) => NotifyOnMemberWarningIssued::money($a);

        [$title, $body] = match ($event->action) {
            'paid' => [
                'Штраф '.$money($w->amount).' сплачено',
                $w->paid_via === 'bank'
                    ? 'Суму списано з вашого рахунку в банку родини. Дякуємо!'
                    : 'Керівництво підтвердило оплату штрафу.',
            ],
            'revoked' => [
                $label.' скасовано',
                'Керівництво скасувало покарання'.($w->resolution_note ? ': «'.$w->resolution_note.'»' : '').'.',
            ],
            'overdue' => [
                'Штраф прострочено — '.$money($w->amount),
                'Штраф '.$money($w->original_amount).' не сплачено вчасно: сума подвоєна до '.$money($w->amount)
                    .' і винесено догану (п. 5.6 правил родини). Сплатіть якнайшвидше.',
            ],
            'expired' => [
                $label.' згоріло',
                $w->type === 'reprimand'
                    ? 'Догана більше не діє — премія знову нараховується повністю.'
                    : 'Зауваження більше не діє. Так тримати!',
            ],
            default => [null, null],
        };

        if ($title === null) {
            return;
        }

        $this->notifications->notify($user, 'member_penalty_updated', $title, $body, NotifyOnMemberWarningIssued::button());

        if ($w->type === 'fine' && class_exists(\Addons\Bonuses\Support\FinanceNotifier::class)) {
            $emoji = $event->action === 'paid' ? '✅' : ($event->action === 'overdue' ? '⏰' : '↩️');
            \Addons\Bonuses\Support\FinanceNotifier::receipt($user, $emoji, $title, $body);
        }
    }
}
