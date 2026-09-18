<?php

namespace Addons\AiAssistant\Services;

use Addons\TelegramBot\Models\TelegramApplication;

/**
 * Короткий "перший погляд" на анкету — не рішення, а підказка рецензенту:
 * чи виглядає відповідь продуманою, чи типовим шаблоном/спамом. Один
 * рядок, що додається до сповіщення про нову заявку.
 */
class ApplicationQualityReviewer
{
    public function __construct(private readonly GeminiClient $gemini)
    {
    }

    public function review(TelegramApplication $application): ?string
    {
        if (! $this->gemini->isConfigured()) {
            return null;
        }

        $about = trim((string) $application->about) ?: '(не заповнено)';

        $prompt = <<<PROMPT
            Ти допомагаєш рецензенту клану (GTA RP) швидко оцінити анкету нового кандидата.
            Нік: {$application->nickname}
            Вік: {$application->age_range}
            Час у грі: {$application->playtime}
            Досвід: {$application->experience}
            Напрямок: {$application->direction}
            Про себе: {$about}

            Одним коротким реченням українською (до 15 слів) дай першу оцінку: чи виглядає анкета продуманою й щирою, чи це типовий шаблон/спам/копіпаста з мінімумом зусиль. Без оцінок типу "схвалити/відхилити" — лише враження. Приклади тону: "Виглядає продумано, конкретні відповіді." або "Дуже коротко, схоже на формальність — варто розпитати додатково."

            Відповідай лише цим реченням, без лапок і пояснень.
            PROMPT;

        return $this->gemini->generateText($prompt);
    }
}
