<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/**
 * Схлопывает накопленные дубликаты аддонов до одной записи на пакет.
 *
 * Загрузка новой версии раньше заводила ВТОРУЮ строку рядом со старой, и
 * в списке аддонов копились одинаковые карточки одного модуля, которые
 * приходилось деактивировать и удалять руками. Теперь установщик
 * обновляет запись на месте, а эта миграция убирает то, что успело
 * накопиться до исправления.
 *
 * Оставляем активную версию; если активной нет — самую свежую по времени
 * загрузки. Файлы прочих версий удаляем: на них уже никто не ссылается.
 */
return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('addons')
            ->select('type', 'slug')
            ->groupBy('type', 'slug')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($groups as $group) {
            $rows = DB::table('addons')
                ->where('type', $group->type)
                ->where('slug', $group->slug)
                ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get();

            $keep = $rows->shift();

            foreach ($rows as $row) {
                // Путь пишет сам установщик из проверенных регуляркой
                // type/slug/version, но перед rmdir всё равно убеждаемся,
                // что не вышли за storage/app/addons.
                $real = realpath(storage_path('app/'.$row->path));
                if ($real && str_starts_with($real, storage_path('app/addons')) && $real !== realpath(storage_path('app/'.$keep->path))) {
                    File::deleteDirectory($real);
                }

                // Журнал установки переносим на выжившую запись, чтобы
                // история пакета не оборвалась вместе с дубликатом.
                DB::table('addon_audit_logs')->where('addon_id', $row->id)->update(['addon_id' => $keep->id]);
                DB::table('addons')->where('id', $row->id)->delete();
            }
        }
    }

    public function down(): void
    {
        // Удалённые дубликаты восстанавливать неоткуда — архивы не хранятся.
    }
};
