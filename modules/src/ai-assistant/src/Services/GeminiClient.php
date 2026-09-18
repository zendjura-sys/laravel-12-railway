<?php

namespace Addons\AiAssistant\Services;

use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонка обгортка над Gemini API (generateContent). Ключ береться з
 * налаштувань (Admin → Налаштування → AI), як і токен Telegram-бота — ніде
 * в коді не зашитий.
 *
 * Жодного модуля-джерела GeminiClient не знає і не імпортує: інші модулі
 * звертаються сюди самі через class_exists(), той самий приём, що й з
 * TelegramClient. Будь-яка помилка (мережа, ключ, формат відповіді) не
 * кидає виняток назовні — викликач отримує null і продовжує без AI,
 * функція сайту не має ламатись через недоступність стороннього сервісу.
 */
class GeminiClient
{
    private const MODEL = 'gemini-3.6-flash';

    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta';

    private ?string $apiKey;

    public function __construct()
    {
        $this->apiKey = Setting::get('gemini_api_key') ?: null;
    }

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    /** Просте текстове звернення — без зображень, без вимоги JSON. */
    public function generateText(string $prompt): ?string
    {
        $response = $this->call(['parts' => [['text' => $prompt]]], []);

        return $this->extractText($response);
    }

    /**
     * Звернення з (опційно) зображеннями, відповідь вимагається строго у
     * форматі JSON — Gemini підтримує responseMimeType: application/json,
     * тому парсити нема потреби вручну шукати ```json блоки в тексті.
     *
     * @param  array<int,array{mime_type:string,data:string}>  $imageParts  data — вже base64
     * @return array<string,mixed>|null
     */
    public function generateJson(string $prompt, array $imageParts = []): ?array
    {
        $parts = [['text' => $prompt]];
        foreach ($imageParts as $image) {
            $parts[] = ['inline_data' => ['mime_type' => $image['mime_type'], 'data' => $image['data']]];
        }

        $response = $this->call(['parts' => $parts], ['responseMimeType' => 'application/json']);
        $text = $this->extractText($response);

        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<string,mixed>  $content
     * @param  array<string,mixed>  $generationConfig
     * @return array<string,mixed>|null
     */
    private function call(array $content, array $generationConfig): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            // Gemini регулярно повертає тимчасовий 503 ("high demand") навіть
            // на робочому ключі — без ретраю кожен такий випадок виглядав би
            // як "AI не працює", хоча за секунду-дві запит зазвичай проходить.
            $response = Http::timeout(30)
                ->retry(2, 2000, fn ($e) => $e instanceof RequestException && $e->response->status() === 503)
                ->asJson()
                ->post(self::API_URL.'/models/'.self::MODEL.':generateContent?key='.$this->apiKey, array_filter([
                    'contents' => [$content],
                    'generationConfig' => $generationConfig ?: null,
                ], static fn ($v) => $v !== null));

            $json = $response->json();

            if (! $response->successful() || ! is_array($json)) {
                Log::warning('gemini: запит не вдався', [
                    'status' => $response->status(),
                    'body' => is_array($json) ? ($json['error']['message'] ?? null) : substr((string) $response->body(), 0, 500),
                ]);

                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('gemini: запит кинув виняток', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /** @param array<string,mixed>|null $response */
    private function extractText(?array $response): ?string
    {
        $text = $response['candidates'][0]['content']['parts'][0]['text'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }
}
