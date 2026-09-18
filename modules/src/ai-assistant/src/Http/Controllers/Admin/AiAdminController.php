<?php

namespace Addons\AiAssistant\Http\Controllers\Admin;

use Addons\AiAssistant\Services\GeminiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAdminController
{
    /**
     * Живий тестовий запит — сама наявність ключа в налаштуваннях ще не
     * означає, що він робочий (неправильний, прострочений, вичерпана
     * квота тощо). Reports/Telegram-адмінка мають той самий приём.
     *
     * Ключ, введений у полі, але ще НЕ збережений — теж приймаємо
     * (api_key): без цього довелось би спершу натиснути "Зберегти", а
     * потім окремо "Перевірити підключення", що плутало.
     */
    public function test(Request $request): JsonResponse
    {
        $typedKey = trim((string) $request->input('api_key', ''));
        $client = $typedKey !== '' ? new GeminiClient($typedKey) : app(GeminiClient::class);

        if (! $client->isConfigured()) {
            return response()->json([
                'ok' => false,
                'message' => 'Спершу вкажіть Gemini API Key.',
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
