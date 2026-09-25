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
        Schema::create('family_events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->dateTime('starts_at');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            // Захист від повторного нагадування, якщо крон випадково
            // прогониться двічі за той самий день до тієї самої події.
            $table->timestamp('reminder_sent_at')->nullable();
            $table->timestamps();

            $table->index('starts_at');
        });

        $permission = Permission::firstOrCreate(['name' => 'events.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('family_events');
        Permission::where('name', 'events.manage')->delete();
    }
};
