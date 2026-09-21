<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Мобільний застосунок раніше качав APK напряму з github.com/releases на
 * телефон — і в частини користувачів це стабільно провалювалось (GitHub
 * недоступний чи обрізається на мобільній мережі/операторі), хоча сам
 * сервер сайту до GitHub достукується без проблем. Тому тепер телефон
 * качає з нашого власного домену, а цей контролер лише проксіює запит
 * до того самого GitHub Release на бекенді — без локального кешу на
 * диску, щоб файл завжди був актуальним одразу після нової CI-збірки.
 */
class MobileDownloadController extends Controller
{
    private const APK_URL = 'https://github.com/zendjura-sys/laravel-12-railway/releases/latest/download/monsory-connect.apk';
    private const BUILD_URL = 'https://github.com/zendjura-sys/laravel-12-railway/releases/latest/download/build.txt';

    public function apk(): StreamedResponse
    {
        return $this->proxy(self::APK_URL, 'monsory-connect.apk', 'application/vnd.android.package-archive');
    }

    public function build(): HttpResponse
    {
        $response = Http::timeout(10)->get(self::BUILD_URL);

        return response($response->body(), $response->status())
            ->header('Content-Type', 'text/plain');
    }

    private function proxy(string $url, string $filename, string $contentType): StreamedResponse
    {
        $upstream = Http::timeout(60)->withOptions(['stream' => true])->get($url);
        $body = $upstream->toPsrResponse()->getBody();

        return response()->stream(function () use ($body) {
            while (! $body->eof()) {
                echo $body->read(1024 * 64);
                flush();
            }
        }, $upstream->status(), [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => $upstream->header('Content-Length'),
        ]);
    }
}
