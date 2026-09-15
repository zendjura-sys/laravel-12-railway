<?php

namespace App\Addons;

use App\Models\Addon;
use Illuminate\Support\Facades\Cache;

/**
 * Регистрирует PSR-4 автозагрузку для активных Module/Plugin без
 * composer dump-autoload — так загруженный через админку аддон начинает
 * работать сразу после активации, без пересборки автозагрузчика.
 */
final class AddonAutoloader
{
    /** @var array<int, array{namespace: string, dir: string}> */
    private array $registered = [];

    public function bootActive(): void
    {
        foreach ($this->activeCodeAddons() as $addon) {
            $manifest = AddonManifest::fromArray($addon['manifest']);
            $baseDir = storage_path('app/' . $addon['path']);

            foreach ($manifest->psr4 as $namespace => $relativeDir) {
                $dir = rtrim($baseDir . '/' . ltrim($relativeDir, '/'), '/');
                if (! is_dir($dir)) {
                    continue;
                }
                $this->registered[] = ['namespace' => $namespace, 'dir' => $dir];
            }
        }

        if ($this->registered === []) {
            return;
        }

        spl_autoload_register(function (string $class): void {
            foreach ($this->registered as $entry) {
                if (! str_starts_with($class, $entry['namespace'])) {
                    continue;
                }
                $relative = substr($class, strlen($entry['namespace']));
                $path = $entry['dir'] . '/' . str_replace('\\', '/', $relative) . '.php';
                if (is_file($path)) {
                    require_once $path;

                    return;
                }
            }
        });
    }

    /**
     * Загружает точки входа активных аддонов для данного контекста
     * (web/admin/bot/api) — просто require нужного routes-файла из
     * манифеста, если он есть.
     */
    public function loadEntrypoint(string $context): void
    {
        foreach ($this->activeCodeAddons() as $addon) {
            $manifest = AddonManifest::fromArray($addon['manifest']);
            $entry = $manifest->entrypoints[$context] ?? null;
            if (! $entry) {
                continue;
            }
            $file = storage_path('app/' . $addon['path'] . '/' . ltrim($entry, '/'));
            if (is_file($file)) {
                require $file;
            }
        }
    }

    /**
     * @return array<int, array{type:string, slug:string, path:string, manifest:array}>
     */
    private function activeCodeAddons(): array
    {
        return Cache::remember('addons.active', 300, function () {
            // На свежем деплое таблица addons может ещё не существовать
            // (миграции этого пакета применяются позже первого запроса) —
            // в этом случае просто ничего не подключаем, а не роняем сайт.
            try {
                return Addon::query()
                    ->whereIn('type', ['core', 'module', 'plugin'])
                    ->where('status', 'active')
                    ->get(['type', 'slug', 'path', 'manifest'])
                    ->map(fn ($a) => [
                        'type' => $a->type,
                        'slug' => $a->slug,
                        'path' => $a->path,
                        'manifest' => $a->manifest,
                    ])
                    ->all();
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
