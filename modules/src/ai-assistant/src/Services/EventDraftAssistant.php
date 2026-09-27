<?php

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Support\AdminInstructions;

/**
 * З короткої підказки адміна пише назву й опис події родини — не дату,
 * час чи локацію: ці поля адмін завжди вводить сам через picker, і AI їх
 * навіть не бачить у промпті, щоб не додати ще одне джерело помилок у
 * датах (мобільні picker'и й так підводять — жодного додаткового ризику).
 */
class EventDraftAssistant
{
    public function __construct(private readonly MistralClient $mistral)
    {
    }

    /** @return array{title:string,description:string}|null */
    public function draft(string $hint): ?array
    {
        if (! $this->mistral->isConfigured() || trim($hint) === '') {
            return null;
        }

        $task = <<<PROMPT
            Ти допомагаєш адміну клану (GTA RP) швидко оформити подію родини за коротким описом.
            Коротка підказка адміна: "{$hint}"

            Напиши українською мовою:
            1. Коротку назву події (до 60 символів)
            2. Опис для учасників (2-4 речення), доброзичливий тон, по суті

            Дату, час і локацію НЕ вигадуй і не згадуй — цих полів тут немає, адмін вкаже їх окремо в інших полях форми.
            PROMPT;

        $format = 'Відповідай ЛИШЕ JSON без пояснень поза ним, у форматі: {"title": "...", "description": "..."}';

        $result = $this->mistral->generateJson(AdminInstructions::insert($task, $format, 'ai_event_draft_instructions'));
        if ($result === null) {
            return null;
        }

        $title = is_string($result['title'] ?? null) ? trim($result['title']) : '';
        $description = is_string($result['description'] ?? null) ? trim($result['description']) : '';

        return $title !== '' ? ['title' => $title, 'description' => $description] : null;
    }
}
