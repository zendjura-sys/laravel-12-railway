<?php

namespace App\Addons;

use InvalidArgumentException;
use ZipArchive;

/**
 * Распаковка ZIP с защитой от zip-slip (path traversal через "../" в именах
 * записей архива) и от исполняемых файлов там, где им быть не положено.
 *
 * Каждая запись архива проверяется ДО извлечения, а не постфактум.
 */
final class SafeZipExtractor
{
    private const MAX_ENTRIES = 5000;
    private const MAX_UNCOMPRESSED_BYTES = 200 * 1024 * 1024; // защита от zip-бомб

    /**
     * @return string Путь к каталогу, куда всё извлечено (может быть
     *                поддиректорией $destination, если архив был обёрнут
     *                в одну общую папку — это нормально для ZIP из
     *                проводника Windows/macOS).
     */
    public function extract(string $zipPath, string $destination, bool $forbidPhp): string
    {
        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            throw new InvalidArgumentException('Не удалось открыть архив — файл повреждён или это не ZIP');
        }

        if ($zip->numFiles === 0) {
            $zip->close();
            throw new InvalidArgumentException('Архив пуст');
        }
        if ($zip->numFiles > self::MAX_ENTRIES) {
            $zip->close();
            throw new InvalidArgumentException('В архиве слишком много файлов (лимит ' . self::MAX_ENTRIES . ')');
        }

        $totalUncompressed = 0;
        $entries = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $name = $stat['name'];

            // символьные ссылки, абсолютные пути и "../" — запрещены целиком
            $normalized = $this->normalizeRelativePath($name);
            if ($normalized === null) {
                $zip->close();
                throw new InvalidArgumentException("Небезопасный путь в архиве: \"$name\"");
            }

            if ($forbidPhp && preg_match('/\.(php|phtml|php[0-9]|phar|phps)$/i', $normalized)) {
                $zip->close();
                throw new InvalidArgumentException(
                    "Design-пакет не может содержать PHP-файлы: \"$name\""
                );
            }

            // .htaccess/.user.ini из темы не должны попадать в публичную папку
            $basename = basename($normalized);
            if ($forbidPhp && in_array(strtolower($basename), ['.htaccess', '.user.ini', 'web.config'], true)) {
                $zip->close();
                throw new InvalidArgumentException("Запрещённый служебный файл в архиве: \"$name\"");
            }

            $totalUncompressed += $stat['size'];
            if ($totalUncompressed > self::MAX_UNCOMPRESSED_BYTES) {
                $zip->close();
                throw new InvalidArgumentException('Архив разворачивается в слишком большой объём данных');
            }

            $entries[$normalized] = $i;
        }

        if (! is_dir($destination) && ! mkdir($destination, 0755, true) && ! is_dir($destination)) {
            $zip->close();
            throw new InvalidArgumentException("Не удалось создать каталог назначения: $destination");
        }

        $destinationReal = rtrim($destination, '/');

        foreach ($entries as $normalized => $index) {
            $target = $destinationReal . '/' . $normalized;

            if (str_ends_with($normalized, '/')) {
                if (! is_dir($target)) {
                    mkdir($target, 0755, true);
                }
                continue;
            }

            $targetDir = dirname($target);
            if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
                $zip->close();
                throw new InvalidArgumentException("Не удалось создать каталог: $targetDir");
            }

            $contents = $zip->getFromIndex($index);
            if ($contents === false) {
                $zip->close();
                throw new InvalidArgumentException("Не удалось прочитать файл из архива: $normalized");
            }

            file_put_contents($target, $contents);
        }

        $zip->close();

        return $this->resolveEffectiveRoot($destinationReal);
    }

    /**
     * Превращает "a/../../etc/passwd" и подобное в null (отказ), а
     * нормальные относительные пути — в чистый вид без ведущего "/".
     */
    private function normalizeRelativePath(string $name): ?string
    {
        $name = str_replace('\\', '/', $name);

        if ($name === '' || str_starts_with($name, '/') || preg_match('#^[A-Za-z]:#', $name)) {
            return null; // абсолютный путь (unix или windows-диск)
        }

        $parts = [];
        foreach (explode('/', $name) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                return null; // попытка выйти за пределы каталога — сразу отказ
            }
            $parts[] = $segment;
        }

        if ($parts === []) {
            return null;
        }

        $result = implode('/', $parts);

        return str_ends_with($name, '/') ? $result . '/' : $result;
    }

    /**
     * Если весь архив обёрнут в одну общую папку (частый случай при ручном
     * зипе через "Отправить -> Сжатая папка"), manifest.json будет не в
     * корне, а на уровень глубже — ищем его и используем эту папку как
     * эффективный корень пакета.
     */
    private function resolveEffectiveRoot(string $extractedRoot): string
    {
        if (is_file($extractedRoot . '/manifest.json')) {
            return $extractedRoot;
        }

        $entries = array_values(array_diff(scandir($extractedRoot) ?: [], ['.', '..']));
        if (count($entries) === 1 && is_dir($extractedRoot . '/' . $entries[0])) {
            $nested = $extractedRoot . '/' . $entries[0];
            if (is_file($nested . '/manifest.json')) {
                return $nested;
            }
        }

        return $extractedRoot;
    }
}
