<?php

namespace Addons\AiAssistant\Services;

use Addons\AiAssistant\Support\AdminInstructions;

/** Стилістичне причепурення чернетки розсилки — сенс і факти не змінює. */
class BroadcastTextAssistant
{
    public function __construct(private readonly MistralClient $mistral)
    {
    }

    public function polish(string $text): ?string
    {
        if (! $this->mistral->isConfigured() || trim($text) === '') {
            return null;
        }

        $task = <<<PROMPT
            Відредагуй цей текст оголошення для учасників ігрового клану (GTA RP) українською мовою: виправ помилки, покращ стиль, зроби чіткішим і доброзичливим. НЕ змінюй факти, дати, суми чи сенс — тільки стиль і граматику. Не додавай нових речень зі своєю інформацією.

            Текст:
            "{$text}"
            PROMPT;

        $format = 'Виведи ЛИШЕ відредагований текст, без лапок і пояснень.';

        return $this->mistral->generateText(AdminInstructions::insert($task, $format, 'ai_broadcast_assist_instructions'));
    }
}
