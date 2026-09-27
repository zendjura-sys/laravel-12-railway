<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Убирает установленные Design-пакеты (аддоны типа theme).
 *
 * Оформление переехало в админку (раздел «Дизайн»), а тип theme из
 * загрузчика удалён. Оставленные в таблице строки указывали бы на
 * маршрут /theme-assets, которого больше нет, и висели бы в списке
 * аддонов как неудаляемый мусор.
 */
return new class extends Migration
{
    public function up(): void
    {
        $themes = DB::table('addons')->where('type', 'theme')->get(['id', 'path']);

        foreach ($themes as $theme) {
            $dir = storage_path('app/'.$theme->path);

            // Пути в таблице пишем мы сами, но перед rmdir всё равно
            // убеждаемся, что не вышли за storage/app/addons.
            $real = realpath($dir);
            if ($real && str_starts_with($real, storage_path('app/addons'))) {
                File::deleteDirectory($real);
            }
        }

        DB::table('addons')->where('type', 'theme')->delete();
    }

    public function down(): void
    {
        // Вернуть удалённые пакеты неоткуда — сами архивы не хранятся.
    }
};
