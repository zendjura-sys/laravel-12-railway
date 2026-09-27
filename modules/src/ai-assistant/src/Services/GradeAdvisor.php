<?php

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Models\AiReportReview;
use Addons\AiAssistant\Support\AdminInstructions;
use Addons\Reports\Models\Report;

/**
 * Пропонує стартову оцінку (S–G) звіту перед затвердженням — підказка
 * модератору у вікні вибору оцінки, не автоматичне рішення: адмін бачить
 * рекомендовану оцінку й коротке пояснення, але сам клікає потрібну кнопку
 * (ту саму чи іншу) і сам підтверджує затвердження.
 */
class GradeAdvisor
{
    public function __construct(private readonly MistralClient $mistral)
    {
    }

    /** @return array{grade:string,reason:?string}|null */
    public function suggest(Report $report): ?array
    {
        if (! $this->mistral->isConfigured()) {
            return null;
        }

        $claim = match ($report->type) {
            'bizwar' => sprintf('бізвар: %d перемог, %d поразок, час капта %s', $report->wins_count ?? 0, $report->losses_count ?? 0, implode(', ', $report->kapt_times ?? [])),
            'contract' => sprintf('контракти: легкий ×%d, середній ×%d, важкий ×%d (разом %d)', $report->light_count ?? 0, $report->medium_count ?? 0, $report->heavy_count ?? 0, ($report->light_count ?? 0) + ($report->medium_count ?? 0) + ($report->heavy_count ?? 0)),
            'investment' => sprintf('інвестиція на суму %s', number_format($report->amount ?? 0, 0, '', ' ')),
            default => (string) $report->description,
        };

        $aiReview = AiReportReview::where('report_id', $report->id)->first();
        $reviewNotes = [];
        if ($aiReview?->date_mismatch) {
            $reviewNotes[] = 'Автоматична перевірка фото знайшла невідповідність дати: '.$aiReview->date_mismatch_detail;
        }
        if ($aiReview?->count_mismatch) {
            $reviewNotes[] = 'Автоматична перевірка фото знайшла невідповідність кількості: '.$aiReview->count_mismatch_detail;
        }
        $reviewText = $reviewNotes === []
            ? 'Автоматична перевірка фото проблем не знайшла (або фото ще не аналізувались).'
            : implode("\n", $reviewNotes);

        $task = <<<PROMPT
            Ти допомагаєш модератору клану (GTA RP) визначити оцінку для щойно поданого звіту учасника перед затвердженням. Оцінка впливає на суму премії за цей звіт: S = +30%, A = +20%, B = +10%, C = без змін, D = -10%, F = -20%, G = -30%.

            Звіт: тип "{$report->type}", деталі: {$claim}.
            {$reviewText}

            Орієнтуйся на загальну якість і результат: об'єктивні показники (перемоги/поразки, обсяг виконаної роботи, сума) і явні проблеми (розбіжності від автоматичної перевірки фото, якщо є). Без явних проблем і з непоганим результатом обирай середню оцінку (B або C) — найвищі (S/A) лише для явно видатного результату, найнижчі (D/F/G) лише за наявності конкретної проблеми.
            PROMPT;

        $format = 'Відповідай ЛИШЕ JSON без пояснень поза ним, у форматі: {"grade": "одна літера з S, A, B, C, D, F, G", "reason": "1 коротке речення пояснення українською"}';

        $result = $this->mistral->generateJson(AdminInstructions::insert($task, $format, 'ai_grade_advice_instructions'));
        if ($result === null) {
            return null;
        }

        $grade = strtoupper(trim((string) ($result['grade'] ?? '')));
        if (! in_array($grade, Report::GRADES, true)) {
            return null;
        }

        $reason = is_string($result['reason'] ?? null) ? trim($result['reason']) : '';

        return ['grade' => $grade, 'reason' => $reason !== '' ? $reason : null];
    }
}
