<?php

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Models\AiReportReview;
use Addons\AiAssistant\Support\AdminInstructions;
use Addons\Reports\Models\Report;
use Illuminate\Support\Facades\Storage;

/**
 * Дивиться на фото-докази щойно поданого звіту й порівнює те, що видно на
 * скріншотах, із заявленими датою та кількістю. Це підказка модератору,
 * а не автоматичне рішення — сам звіт як був "на розгляді", так і
 * лишається, жодного статусу ця перевірка не міняє.
 */
class ReportPhotoAnalyzer
{
    public function __construct(private readonly MistralClient $mistral)
    {
    }

    public function analyze(Report $report): void
    {
        if (! $this->mistral->isConfigured()) {
            return;
        }

        $attachments = $report->attachments()->get();
        if ($attachments->isEmpty()) {
            return;
        }

        $imageParts = [];
        foreach ($attachments->take(10) as $attachment) {
            $path = $attachment->getRawOriginal('disk_path');
            $bytes = Storage::disk('public')->get($path);
            if ($bytes === null) {
                continue;
            }

            $imageParts[] = [
                'mime_type' => Storage::disk('public')->mimeType($path) ?: 'image/jpeg',
                'data' => base64_encode($bytes),
            ];
        }

        if ($imageParts === []) {
            return;
        }

        $result = $this->mistral->generateJson($this->buildPrompt($report, $attachments->count()), $imageParts);
        if ($result === null) {
            return;
        }

        $dateMismatch = (bool) ($result['date_mismatch'] ?? false);
        $countMismatch = (bool) ($result['count_mismatch'] ?? false);

        AiReportReview::updateOrCreate(['report_id' => $report->id], [
            'flagged' => $dateMismatch || $countMismatch,
            'date_mismatch' => $dateMismatch,
            'date_mismatch_detail' => $this->str($result['date_mismatch_detail'] ?? null),
            'count_mismatch' => $countMismatch,
            'count_mismatch_detail' => $this->str($result['count_mismatch_detail'] ?? null),
            'notes' => $this->str($result['notes'] ?? null),
        ]);
    }

    private function buildPrompt(Report $report, int $photoCount): string
    {
        $date = $report->report_date?->format('d.m.Y');
        $claim = match ($report->type) {
            'bizwar' => sprintf('бізвар: %d перемог, %d поразок, час капта %s', $report->wins_count ?? 0, $report->losses_count ?? 0, implode(', ', $report->kapt_times ?? [])),
            'contract' => sprintf('контракти: легкий ×%d, середній ×%d, важкий ×%d (разом %d)', $report->light_count ?? 0, $report->medium_count ?? 0, $report->heavy_count ?? 0, ($report->light_count ?? 0) + ($report->medium_count ?? 0) + ($report->heavy_count ?? 0)),
            'investment' => sprintf('інвестиція на суму %s', number_format($report->amount ?? 0, 0, '', ' ')),
            default => (string) $report->description,
        };

        $task = <<<PROMPT
            Ти допомагаєш модератору перевірити звіт учасника гри (GTA RP клан).
            Учасник заявив: дата звіту {$date}, тип "{$report->type}", деталі: {$claim}.
            Додано {$photoCount} фото-доказ(ів) — скріншотів гри.

            Перевір ДВІ речі, тільки якщо є явна, очевидна невідповідність (не вигадуй й не додумуй, якщо не впевнений — просто залиш false):

            1. Дата на скріншоті — ЛИШЕ календарна дата (день.місяць.рік), більше нічого. У грі вона зазвичай видно в нижньому лівому куті екрана (телефон/годинник у грі) або в чаті. Порівняй її ЦІЛИКОМ як одне значення з датою звіту ({$date}). Якщо дата на фото чітко видно і вона НЕ збігається з датою звіту — постав date_mismatch true й одним коротким реченням напиши, яка дата на фото і яка в звіті (наприклад: "на фото 17.09.2026, у звіті 18.09.2026").
               ВАЖЛИВО: час доби (години:хвилини) на скріншотах НЕ перевіряй і НЕ згадуй у поясненні — він природно відрізняється між різними скріншотами одного дня і це нормально, це НЕ помилка. Порівнюй тільки дату як єдине ціле — ніколи не розбивай її на день/місяць/рік окремо і не пиши "рік вказано як X, а не Y", якщо X і Y однакові чи взагалі про рік нема явної розбіжності.

            2. Кількість. Порівняй кількість наданих фото ({$photoCount}) із заявленою кількістю виконаних робіт/результатів. Якщо очевидно замало доказів на заявлену кількість (наприклад, один скріншот на 5 контрактів) — постав count_mismatch true й поясни просто.
            PROMPT;

        $format = <<<PROMPT
            Відповідай ЛИШЕ JSON без пояснень поза ним, у форматі:
            {"date_mismatch": true/false, "date_mismatch_detail": "коротке пояснення простими словами українською або null", "count_mismatch": true/false, "count_mismatch_detail": "коротке пояснення або null", "notes": "1 речення загального враження або null"}
            PROMPT;

        return AdminInstructions::insert($task, $format, 'ai_reports_analysis_instructions');
    }

    private function str(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value !== '' && $value !== null && strtolower($value) !== 'null' ? $value : null;
    }
}
