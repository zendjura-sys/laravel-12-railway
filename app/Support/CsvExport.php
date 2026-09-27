<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Спільний для будь-якого модуля потоковий CSV-експорт — щоб не писати
 * ту саму BOM+fputcsv возню в кожному контролері окремо. Проходить
 * $rows один раз, у пам'яті лишається лише поточний рядок.
 *
 * @param  array<int, string>  $headers
 * @param  iterable<array<int, mixed>>  $rows
 */
class CsvExport
{
    public static function stream(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return new StreamedResponse(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            // BOM — інакше Excel на Windows (найпоширеніший сценарій для
            // "відкрити CSV") показує кирилицю трапалугою через UTF-8 без
            // мітки.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, $row, ';');
            }
            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
