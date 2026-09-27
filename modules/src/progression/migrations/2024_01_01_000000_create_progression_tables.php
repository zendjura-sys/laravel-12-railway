<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Один денормализованный профиль на участника — быстрое чтение для
        // leaderboard/карточки без пересчёта по всему xp_ledger каждый раз.
        Schema::create('progression_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('xp')->default(0);
            $table->unsignedInteger('current_streak')->default(0);
            $table->unsignedInteger('longest_streak')->default(0);
            $table->unsignedInteger('kapt_wins')->default(0);
            $table->unsignedInteger('kapt_losses')->default(0);
            $table->unsignedInteger('contracts_count')->default(0);
            $table->unsignedInteger('heavy_contracts_count')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        // Апенд-лог каждого начисления XP — источник правды для аудита и
        // "Battle Log"; progression_profiles.xp — это просто SUM() отсюда,
        // закэшированный для скорости.
        Schema::create('xp_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount');
            $table->string('reason');
            $table->enum('source_type', ['report', 'weekly_bonus', 'manual']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('achievements', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('user_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('achievement_id')->constrained('achievements')->cascadeOnDelete();
            $table->timestamp('earned_at');

            $table->unique(['user_id', 'achievement_id']);
        });

        // Каталог достижений — фиксированный набор из брифа, не редактируется
        // через UI на этом этапе.
        $now = now();
        DB::table('achievements')->insert([
            ['code' => 'first_blood', 'name' => 'First Blood', 'description' => 'Перша перемога KAPT', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'streak_5', 'name' => 'Серія: 5', 'description' => '5 перемог KAPT поспіль', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'streak_15', 'name' => 'Серія: 15', 'description' => '15 перемог KAPT поспіль', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'streak_30', 'name' => 'Серія: 30', 'description' => '30 перемог KAPT поспіль', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'contracts_25', 'name' => '25 контрактів', 'description' => '25 виконаних контрактів', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'contracts_100', 'name' => '100 контрактів', 'description' => '100 виконаних контрактів', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'contracts_250', 'name' => '250 контрактів', 'description' => '250 виконаних контрактів', 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'sharpshooter', 'name' => 'Sharpshooter', 'description' => '10 контрактів рівня heavy', 'created_at' => $now, 'updated_at' => $now],
        ]);

        $permission = Permission::firstOrCreate(['name' => 'progression.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_achievements');
        Schema::dropIfExists('achievements');
        Schema::dropIfExists('xp_ledger');
        Schema::dropIfExists('progression_profiles');
        Permission::where('name', 'progression.manage')->delete();
    }
};
