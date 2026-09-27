<?php

namespace Addons\AiAssistant\Services;

use App\Models\Setting;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Тонка обгортка над Mistral API (chat/completions). Ключ береться з
 * налаштувань (Admin → Налаштування → AI), як і токен Telegram-бота — ніде
 * в коді не зашитий.
 *
 * Модель — псевдонім "-latest" (не дата-снапшот): Mistral сам підмінює
 * його на актуальну модель лінійки, коли стару знімають з підтримки (так
 * і сталось із попередником, pixtral-large-latest, — застарів і був
 * знятий, поки ми ще навіть не встигли ним скористатись).
 *
 * Жодного модуля-джерела MistralClient не знає і не імпортує: інші модулі
 * звертаються сюди самі через class_exists(), той самий приём, що й з
 * TelegramClient. Будь-яка помилка (мережа, ключ, формат відповіді) не
 * кидає виняток назовні — викликач отримує null і продовжує без AI,
 * функція сайту не має ламатись через недоступність стороннього сервісу.
 */
class MistralClient
{
    private const MODEL = 'mistral-medium-latest';

    private const API_URL = 'https://api.mistral.ai/v1/chat/completions';

    private ?string $apiKey;

    private ?string $proxyUrl;

    /**
     * $overrideKey/$overrideProxy — для перевірки підключення в адмінці ще
     * ДО збереження: без цього "Перевірити підключення" тестував би те, що
     * вже лежить у налаштуваннях, а не щойно введене в полі значення.
     *
     * $proxyUrl — про запас, на випадок якщо сам сервер колись буде
     * заблокований на мережевому рівні (так уже було з Gemini, звідки й
     * перейшли на Mistral) — усі запити тоді підуть через проксі.
     */
    public function __construct(?string $overrideKey = null, ?string $overrideProxy = null)
    {
        $this->apiKey = $overrideKey ?: (Setting::get('mistral_api_key') ?: null);
        $this->proxyUrl = $overrideProxy ?: (Setting::get('mistral_proxy_url') ?: null);
    }

    public function isConfigured(): bool
    {
        return (bool) $this->apiKey;
    }

    /** Просте текстове звернення — без зображень, без вимоги JSON. */
    public function generateText(string $prompt): ?string
    {
        $response = $this->call([['type' => 'text', 'text' => $prompt]], jsonMode: false);

        return $this->extractText($response);
    }

    /**
     * Звернення з (опційно) зображеннями, відповідь вимагається строго у
     * форматі JSON — response_format: json_object, тому нема потреби
     * вручну шукати ```json блоки в тексті. Промпт при цьому все одно має
     * явно просити JSON (вимога самого Mistral для цього режиму) — у всіх
     * викликачів вона вже є.
     *
     * @param  array<int,array{mime_type:string,data:string}>  $imageParts  data — вже base64
     * @return array<string,mixed>|null
     */
    public function generateJson(string $prompt, array $imageParts = []): ?array
    {
        $content = [['type' => 'text', 'text' => $prompt]];
        foreach ($imageParts as $image) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => 'data:'.$image['mime_type'].';base64,'.$image['data']],
            ];
        }

        $response = $this->call($content, jsonMode: true);
        $text = $this->extractText($response);

        if ($text === null) {
            return null;
        }

        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param  array<int,array<string,mixed>>  $content
     * @return array<string,mixed>|null
     */
    private function call(array $content, bool $jsonMode): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        try {
            // 429 (rate limit) і 503 — типові тимчасові стани Mistral під
            // навантаженням, без ретраю кожен такий випадок виглядав би як
            // "AI не працює". throw: false — інакше retry() сам кидає
            // виняток після невдачі (навіть якщо when() відмовив у
            // повторі), і повне тіло помилки губиться за куцим
            // повідомленням замість детального логування нижче.
            $request = Http::timeout(30)
                ->retry(2, 2000, fn ($e) => $e instanceof RequestException && in_array($e->response->status(), [429, 503], true), throw: false)
                ->withToken($this->apiKey)
                ->asJson();

            if ($this->proxyUrl) {
                $request = $request->withOptions(['proxy' => $this->proxyUrl]);
            }

            $body = [
                'model' => self::MODEL,
                'messages' => [['role' => 'user', 'content' => $content]],
            ];
            if ($jsonMode) {
                $body['response_format'] = ['type' => 'json_object'];
            }

            $response = $request->post(self::API_URL, $body);

            $json = $response->json();

            if (! $response->successful() || ! is_array($json)) {
                Log::warning('mistral: запит не вдався', [
                    'status' => $response->status(),
                    'body' => is_array($json) ? ($json['message'] ?? $json['error']['message'] ?? $json) : substr((string) $response->body(), 0, 800),
                ]);

                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            // Ключ тут іде в заголовку Authorization, а не в query-рядку
            // URL (як було з Gemini), тож у типовий cURL-виняток він не
            // потрапляє — але редагуємо про всяк випадок (TLS/proxy
            // помилки іноді відлунюють заголовки запиту в тексті).
            Log::warning('mistral: запит кинув виняток', ['error' => $this->redactKey($e->getMessage())]);

            return null;
        }
    }

    private function redactKey(string $message): string
    {
        return $this->apiKey ? str_replace($this->apiKey, '[REDACTED]', $message) : $message;
    }

    /** @param array<string,mixed>|null $response */
    private function extractText(?array $response): ?string
    {
        $text = $response['choices'][0]['message']['content'] ?? null;

        return is_string($text) && trim($text) !== '' ? $text : null;
    }
}
