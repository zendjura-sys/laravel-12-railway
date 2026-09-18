<?php

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Models\AiReportReview;
use Addons\Reports\Models\Report;

/**
 * Пише просте, дружнє пояснення для учасника: що саме поправити, щоб звіт
 * прийняли наступного разу. Адмін бачить це як ГОТОВИЙ ЧЕРНЕТКОВИЙ текст у
 * полі причини відхилення — редагує чи прибирає перед тим, як реально
 * натиснути "Відхилити". AI нічого не відхиляє сам.
 */
class RejectionAdvisor
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    /** @return string|null null — якщо AI недоступний або не зміг згенерувати текст */
    public function draft(Report $report, ?string $adminHint = null): ?string
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $aiReview = AiReportReview::where('report_id', $report->id)->first();

        $context = [];
        if ($aiReview?->date_mismatch) {
            $context[] = 'Автоматична перевірка знайшла невідповідність дати на скріншоті: '.$aiReview->date_mismatch_detail;
        }
        if ($aiReview?->count_mismatch) {
            $context[] = 'Автоматична перевірка знайшла невідповідність кількості: '.$aiReview->count_mismatch_detail;
        }
        if ($adminHint) {
            $context[] = 'Коментар модератора: '.$adminHint;
        }

        $contextText = $context === [] ? 'Причину модератор окремо не вказав — напиши загальну пораду під цей тип звіту.' : implode("\n", $context);

        $prompt = <<<PROMPT
            Ти пишеш коротке повідомлення учаснику ігрового клану (GTA RP), чий звіт (тип "{$report->type}") щойно відхилили.
            Контекст, чому відхилили:
            {$contextText}

            Напиши українською, простими словами, без канцеляриту, 2-4 речення: що саме людині зробити, щоб наступний звіт прийняли (наприклад: "подайте звіт, вказавши дату зі скріншотів", "додайте по одному фото на кожну виконану роботу" тощо — залежно від контексту вище). Тон доброзичливий, без образ, по суті. БЕЗ привітань типу "Привіт" і БЕЗ підпису в кінці — тільки сама порада.
            PROMPT;

        return $this->gemini->generateText($prompt);
    }
}
