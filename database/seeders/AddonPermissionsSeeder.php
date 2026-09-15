<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Право на управление системой аддонов (Core/Modules/Plugins/Themes) и
 * роль "admin", которой оно выдано. Запускать один раз при разворачивании:
 * php artisan db:seed --class=Database\\Seeders\\AddonPermissionsSeeder
 *
 * Кому конкретно дать роль "admin" — решается отдельно, вручную
 * (см. инструкцию в чате), чтобы не выдавать доступ никому просто по факту сидирования.
 */
class AddonPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permission = Permission::firstOrCreate(['name' => 'addons.manage', 'guard_name' => 'web']);

        $role = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
    }
}
