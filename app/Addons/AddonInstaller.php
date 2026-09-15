<?php

namespace App\Addons;

use App\Models\Addon;
use App\Models\AddonAuditLog;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use ZipArchive;

/**
 * Единая точка входа для загрузчиков Core/Modules/Plugins/Themes.
 *
 * Каждый шаг (валидация -> распаковка -> проверка манифеста -> перенос в
 * постоянное хранилище -> запись в БД) явный и может упасть с понятной
 * ошибкой — она и уходит в поле errors AJAX-ответа контроллера.
 */
final class AddonInstaller
{
    private const STORAGE_ROOT = 'addons'; // относительно storage/app

    public function __construct(
        private readonly SafeZipExtractor $extractor,
    ) {
    }

    /**
     * @param string $expectedType Тип, под который загружали (какой именно
     *                             loader вызвали) — должен совпасть с
     *                             manifest.type, иначе это явная попытка
     *                             протащить Core через Themes loader и т.п.
     */
    public function install(UploadedFile $file, string $expectedType, ?User $actor): Addon
    {
        if (! in_array($expectedType, AddonManifest::TYPES, true)) {
            throw new InvalidArgumentException('Неизвестный тип загрузчика');
        }

        if (strtolower($file->getClientOriginalExtension()) !== 'zip') {
            throw new InvalidArgumentException('Ожидается ZIP-архив');
        }

        $tempDir = storage_path('app/tmp/addon-install-' . Str::random(20));
        $extractDir = $tempDir . '/extracted';

        try {
            $zipPath = $file->getRealPath();
            if ($zipPath === false || ! is_file($zipPath)) {
                throw new InvalidArgumentException('Не удалось прочитать загруженный файл');
            }

            // быстрая проверка целостности архива до полной распаковки
            $probe = new ZipArchive();
            if ($probe->open($zipPath) !== true) {
                throw new InvalidArgumentException('Архив повреждён или имеет неверный формат');
            }
            $probe->close();

            $root = $this->extractor->extract($zipPath, $extractDir, forbidPhp: $expectedType === 'theme');

            $manifestPath = $root . '/manifest.json';
            if (! is_file($manifestPath)) {
                throw new InvalidArgumentException('В архиве нет manifest.json в корне пакета');
            }

            $manifestData = json_decode((string) file_get_contents($manifestPath), true);
            if (! is_array($manifestData)) {
                throw new InvalidArgumentException('manifest.json повреждён или не является валидным JSON');
            }

            $manifest = AddonManifest::fromArray($manifestData);

            if ($manifest->type !== $expectedType) {
                throw new InvalidArgumentException(sprintf(
                    'Несовпадение типа пакета: архив загружен через загрузчик "%s", а manifest.type = "%s"',
                    AddonManifest::labelForType($expectedType),
                    $manifest->type,
                ));
            }

            $existing = Addon::query()
                ->where('type', $manifest->type)
                ->where('slug', $manifest->slug)
                ->where('version', $manifest->version)
                ->first();
            if ($existing) {
                throw new InvalidArgumentException(
                    "Версия {$manifest->version} пакета \"{$manifest->slug}\" уже загружена"
                );
            }

            $permanentPath = self::STORAGE_ROOT . "/{$manifest->type}/{$manifest->slug}/{$manifest->version}";
            $permanentAbsolute = storage_path('app/' . $permanentPath);

            if (is_dir($permanentAbsolute)) {
                throw new RuntimeException('Каталог назначения уже существует — возможен незавершённый предыдущий импорт');
            }

            // rename() не создаёт промежуточные каталоги сам — storage/app/addons/{type}/{slug}/
            // на этот момент может ещё не существовать (первый пакет этого типа/slug).
            $parentDir = dirname($permanentAbsolute);
            if (! is_dir($parentDir) && ! mkdir($parentDir, 0755, true) && ! is_dir($parentDir)) {
                throw new RuntimeException('Не удалось создать каталог хранилища аддонов');
            }

            if (! rename($root, $permanentAbsolute)) {
                throw new RuntimeException('Не удалось перенести файлы пакета в постоянное хранилище');
            }

            $addon = DB::transaction(function () use ($manifest, $permanentPath, $actor) {
                $addon = Addon::create([
                    'type' => $manifest->type,
                    'slug' => $manifest->slug,
                    'name' => $manifest->name,
                    'version' => $manifest->version,
                    'manifest' => $manifest->raw,
                    'status' => 'inactive',
                    'path' => $permanentPath,
                    'migrations_applied' => false,
                    'installed_by' => $actor?->id,
                ]);

                AddonAuditLog::create([
                    'addon_id' => $addon->id,
                    'user_id' => $actor?->id,
                    'action' => 'installed',
                    'meta' => ['version' => $manifest->version, 'slug' => $manifest->slug],
                    'ip' => request()?->ip(),
                ]);

                return $addon;
            });

            return $addon;
        } finally {
            $this->cleanupDir($tempDir);
        }
    }

    public function activate(Addon $addon, ?User $actor): void
    {
        DB::transaction(function () use ($addon, $actor) {
            // Core и Theme — эксклюзивны: активна только одна версия/тема сразу.
            if (in_array($addon->type, ['core', 'theme'], true)) {
                Addon::query()
                    ->where('type', $addon->type)
                    ->where('id', '!=', $addon->id)
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
            } else {
                // Module/Plugin: нельзя иметь две активные версии ОДНОГО slug одновременно.
                Addon::query()
                    ->where('type', $addon->type)
                    ->where('slug', $addon->slug)
                    ->where('id', '!=', $addon->id)
                    ->where('status', 'active')
                    ->update(['status' => 'inactive']);
            }

            $addon->update(['status' => 'active']);

            AddonAuditLog::create([
                'addon_id' => $addon->id,
                'user_id' => $actor?->id,
                'action' => 'activated',
                'meta' => ['version' => $addon->version],
                'ip' => request()?->ip(),
            ]);
        });

        Cache::forget('addons.active');
    }

    public function deactivate(Addon $addon, ?User $actor): void
    {
        $addon->update(['status' => 'inactive']);

        AddonAuditLog::create([
            'addon_id' => $addon->id,
            'user_id' => $actor?->id,
            'action' => 'deactivated',
            'ip' => request()?->ip(),
        ]);

        Cache::forget('addons.active');
    }

    public function uninstall(Addon $addon, ?User $actor): void
    {
        if ($addon->isActive()) {
            throw new InvalidArgumentException('Сначала деактивируйте пакет — нельзя удалить активный');
        }

        $absolute = storage_path('app/' . $addon->path);
        $this->cleanupDir($absolute);

        AddonAuditLog::create([
            'addon_id' => null, // сам аддон сейчас удалим, ссылку не оставляем
            'user_id' => $actor?->id,
            'action' => 'uninstalled',
            'meta' => ['type' => $addon->type, 'slug' => $addon->slug, 'version' => $addon->version],
            'ip' => request()?->ip(),
        ]);

        $addon->delete();
        Cache::forget('addons.active');
    }

    /**
     * Прогоняет миграции аддона. НЕ вызывается автоматически при установке —
     * только по явному действию администратора в панели.
     */
    public function applyMigrations(Addon $addon, ?User $actor): array
    {
        $manifest = AddonManifest::fromArray($addon->manifest);

        if (! $manifest->migrationsPath) {
            throw new InvalidArgumentException('У пакета нет объявленных миграций');
        }

        $migrationsAbsolute = storage_path('app/' . $addon->path . '/' . ltrim($manifest->migrationsPath, '/'));
        if (! is_dir($migrationsAbsolute)) {
            throw new InvalidArgumentException('Каталог миграций не найден в пакете');
        }

        try {
            Artisan::call('migrate', [
                '--path' => $migrationsAbsolute,
                '--realpath' => true,
                '--force' => true,
            ]);
            $output = Artisan::output();
        } catch (Throwable $e) {
            $addon->update(['status' => 'failed', 'last_error' => $e->getMessage()]);

            AddonAuditLog::create([
                'addon_id' => $addon->id,
                'user_id' => $actor?->id,
                'action' => 'failed',
                'meta' => ['stage' => 'migrations', 'error' => $e->getMessage()],
                'ip' => request()?->ip(),
            ]);

            throw new RuntimeException('Ошибка применения миграций: ' . $e->getMessage(), previous: $e);
        }

        $addon->update(['migrations_applied' => true, 'last_error' => null]);

        AddonAuditLog::create([
            'addon_id' => $addon->id,
            'user_id' => $actor?->id,
            'action' => 'migrations_applied',
            'meta' => ['output' => $output],
            'ip' => request()?->ip(),
        ]);

        return ['output' => $output];
    }

    private function cleanupDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @unlink($dir);

            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($dir);
    }
}
