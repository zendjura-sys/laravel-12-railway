<?php

namespace App\Http\Controllers;

use App\Addons\AddonManifest;
use App\Models\Addon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Отдаёт статические файлы активной темы (Design-пакета).
 *
 * Файлы темы НИКОГДА не кладутся напрямую в public/ — они лежат в
 * storage/app/addons/theme/... и стримятся через этот контроллер с жёстким
 * белым списком расширений, чтобы загруженный через админку архив не мог
 * стать вектором для исполнения кода на сервере.
 */
class ThemeAssetController extends Controller
{
    private const ALLOWED_EXTENSIONS = [
        'css', 'js', 'mjs', 'json',
        'png', 'jpg', 'jpeg', 'webp', 'svg', 'gif', 'ico',
        'woff', 'woff2', 'ttf', 'otf',
    ];

    private const MIME_MAP = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'mjs' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    public function show(string $path): StreamedResponse|Response
    {
        $theme = Cache::remember('addons.active_theme', 300, function () {
            try {
                return Addon::query()
                    ->where('type', 'theme')
                    ->where('status', 'active')
                    ->first(['slug', 'path', 'manifest']);
            } catch (\Throwable) {
                return null;
            }
        });

        if (! $theme) {
            abort(404);
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            abort(404);
        }

        // защита от "../" в самом пути запроса — те же правила, что при распаковке
        $segments = [];
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                abort(404);
            }
            $segments[] = $segment;
        }

        $manifest = AddonManifest::fromArray($theme->manifest);
        $assetsDir = trim((string) $manifest->assetsPath, '/') ?: 'assets';

        $absolute = storage_path('app/' . $theme->path . '/' . $assetsDir . '/' . implode('/', $segments));

        if (! is_file($absolute)) {
            abort(404);
        }

        return response()->file($absolute, [
            'Content-Type' => self::MIME_MAP[$extension] ?? 'application/octet-stream',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }
}
