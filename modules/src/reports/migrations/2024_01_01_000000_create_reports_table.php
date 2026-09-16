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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();

            // Кому засчитывается отчёт (не обязательно тот, кто его подал —
            // офицер может подать отчёт за другого участника).
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('submitted_by')->constrained('users')->cascadeOnDelete();

            $table->enum('type', ['kapt', 'contract', 'other']);
            // outcome — только для type=kapt, weight — только для type=contract.
            // Оба nullable и не смешиваются: тип определяет, какое поле валидно.
            $table->enum('outcome', ['win', 'loss'])->nullable();
            $table->enum('weight', ['light', 'medium', 'heavy'])->nullable();

            $table->text('description')->nullable();

            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['type', 'status']);
        });

        // Модуль сам заводит себе право и выдаёт его роли admin — не
        // полагаемся на то, что Core или кто-то ещё об этом позаботится.
        $permission = Permission::firstOrCreate(['name' => 'reports.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
        Permission::where('name', 'reports.manage')->delete();
    }
};
