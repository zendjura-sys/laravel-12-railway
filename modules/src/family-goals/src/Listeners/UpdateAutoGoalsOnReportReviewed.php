<?php

namespace Addons\FamilyGoals\Listeners;

use Addons\FamilyGoals\Events\FamilyGoalCompleted;
use Addons\FamilyGoals\Models\ActivityEvent;
use Addons\FamilyGoals\Models\FamilyGoal;
use Addons\Reports\Models\Report;
use Illuminate\Support\Facades\Event;

/**
 * Рухає цілі з metric !== null самостійно, зі звітів — без участі адміна.
 * Цілі з metric === null (їх переважна більшість, і всі старі) цей
 * listener не чіпає взагалі: для них прогрес і далі виставляється вручну
 * через "Оновити прогрес" в адмінці, як і раніше.
 *
 * На відміну від "Розкладу капта", тут дедуплікація НЕ потрібна: бізвар —
 * єдиний спільний бій, а контракти/інвестиції різних людей — це справді
 * різний внесок кожного, і кожен звіт рахується сам за себе.
 */
class UpdateAutoGoalsOnReportReviewed
{
    public function handle($event): void
    {
        $report = $event->report;
        if ($report->status !== 'approved') {
            return;
        }

        // reports_count рухає БУДЬ-який тип звіту.
        $this->bump('reports_count', 1);

        [$metric, $amount] = $this->typeSpecific($report);
        if ($metric !== null && $amount > 0) {
            $this->bump($metric, $amount);
        }
    }

    /** @return array{0:?string,1:int} */
    private function typeSpecific(Report $report): array
    {
        return match ($report->type) {
            'bizwar' => ['bizwar_wins', (int) ($report->wins_count ?? 0)],
            'contract' => ['contracts_count', $report->weight !== null
                ? 1
                : (int) (($report->light_count ?? 0) + ($report->medium_count ?? 0) + ($report->heavy_count ?? 0)),
            ],
            'investment' => ['investment_total', (int) ($report->amount ?? 0)],
            default => [null, 0],
        };
    }

    private function bump(string $metric, int $amount): void
    {
        FamilyGoal::query()
            ->where('status', 'active')
            ->where('metric', $metric)
            ->get()
            ->each(function (FamilyGoal $goal) use ($amount) {
                if ($goal->applyIncrement($amount)) {
                    ActivityEvent::log('goal_completed', null, "Ціль «{$goal->title}» досягнута! 🎉");
                    Event::dispatch(new FamilyGoalCompleted($goal));
                }
            });
    }
}
