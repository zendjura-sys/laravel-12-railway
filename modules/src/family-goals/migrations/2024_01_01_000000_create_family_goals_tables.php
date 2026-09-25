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
        Schema::create('family_goals', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('target_value')->nullable();
            $table->unsignedInteger('current_value')->default(0);
            $table->string('unit')->nullable();
            $table->date('deadline')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // Спільна стрічка: власні події модуля (створення/прогрес/завершення
        // цілі) плюс те, що прилетить ззовні через routes/events.php.
        // message зберігається вже готовим текстом, а не зібраним із type —
        // формати подій різних модулів надто різні, щоб уніфікувати на льоту.
        Schema::create('activity_events', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message');
            $table->timestamps();
        });

        $permission = Permission::firstOrCreate(['name' => 'goals.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_events');
        Schema::dropIfExists('family_goals');
        Permission::where('name', 'goals.manage')->delete();
    }
};
