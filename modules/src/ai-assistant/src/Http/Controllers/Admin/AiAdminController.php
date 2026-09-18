<?php

namespace Addons\AiAssistant\Http\Controllers\Admin;

use Addons\AiAssistant\Services\GeminiClient;
use Illuminate\Http\JsonResponse;

class AiAdminController
{
    /**
     * Живий тестовий запит — сама наявність ключа в налаштуваннях ще не
     * означає, що він робочий (неправильний, прострочений, вичерпана
     * квота тощо). Reports/Telegram-адмінка мають той самий приём.
     */
    public function test(): JsonResponse
    {
        $client = app(GeminiClient::class);

        if (! $client->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Спершу вкажіть Gemini API Key і збережіть.',
                'data' => null,
                'errors' => null,
                'redirect' => null,
            ], 422);
        }

        $reply = $client->generateText('Відповідай рівно одним словом: OK.');

        return response()->json([
            'ok' => $reply !== null,
            'message' => $reply !== null
                ? 'Підключення працює.'
                : 'Не вдалося отримати відповідь від Gemini — перевірте ключ і логи сервера.',
            'data' => null,
            'errors' => null,
            'redirect' => null,
        ], $reply !== null ? 200 : 422);
    }
}
