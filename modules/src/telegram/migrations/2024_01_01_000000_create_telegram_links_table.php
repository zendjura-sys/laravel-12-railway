<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Рядок створюється одразу, коли учасник тисне "Прив'язати" на сайті
        // (user_id + link_code, chat_id ще NULL) — бот заповнює chat_id/
        // username/linked_at, коли код прийде через /start CODE.
        Schema::create('telegram_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('chat_id')->nullable()->unique();
            $table->string('telegram_username')->nullable();
            $table->string('link_code', 12)->nullable()->unique();
            $table->timestamp('code_expires_at')->nullable();
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        $permission = Permission::firstOrCreate(['name' => 'telegram.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_links');
        Permission::where('name', 'telegram.manage')->delete();
    }
};
