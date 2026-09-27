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
        // Єдиний рядок конфігурації — усі ставки/пороги, нуль за замовчуванням
        // (нічого не нараховується, доки адмін явно не вкаже цифри).
        Schema::create('bonus_settings', function (Blueprint $table) {
            $table->id();
            // За 100% winrate бізвар-каптерів за тиждень. Реальна виплата —
            // rate * winrate (наприклад 90% -> 0.9 * rate).
            $table->unsignedBigInteger('bizwar_base_rate')->default(0);
            $table->unsignedBigInteger('contract_light_rate')->default(0);
            $table->unsignedBigInteger('contract_medium_rate')->default(0);
            $table->unsignedBigInteger('contract_heavy_rate')->default(0);
            // Поріг і сума бонусу за серію перемог поспіль (current_streak
            // з Progression). NULL поріг = бонус вимкнено.
            $table->unsignedInteger('streak_threshold')->nullable();
            $table->unsignedBigInteger('streak_bonus_amount')->default(0);
            // Поріг і сума бонусу за кількість виконаних контрактів за тиждень.
            $table->unsignedInteger('contracts_count_threshold')->nullable();
            $table->unsignedBigInteger('contracts_count_bonus_amount')->default(0);
            // У дайджест і список нарахувань потрапляють лише суми строго
            // вище цього порогу (за брифом — "вище 1₴").
            $table->unsignedBigInteger('min_digest_amount')->default(1);
            $table->timestamps();
        });

        // Список тірів — "Інвестор I/II/III...", кожен зі своїм порогом
        // кумулятивної суми інвестицій і одноразовою премією за досягнення.
        Schema::create('investment_achievement_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->unsignedBigInteger('threshold_amount');
            $table->unsignedBigInteger('bonus_amount');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Один рядок = один юзер за один тиждень, з повною розбивкою —
        // цей рядок сам є аудит-слідом (хто/коли/скільки/за що), окремого
        // логу не потрібно, той самий підхід, що й у Broadcasts.
        Schema::create('bonus_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('week_start');
            $table->unsignedBigInteger('bizwar_amount')->default(0);
            $table->decimal('bizwar_winrate', 5, 2)->nullable();
            $table->unsignedBigInteger('contract_amount')->default(0);
            $table->unsignedInteger('contracts_count')->default(0);
            $table->unsignedBigInteger('streak_bonus_amount')->default(0);
            $table->unsignedBigInteger('contracts_count_bonus_amount')->default(0);
            $table->unsignedBigInteger('investment_bonus_amount')->default(0);
            $table->unsignedBigInteger('total_amount')->default(0);
            $table->boolean('paid')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'week_start']);
        });

        // Які тіри інвестицій юзер уже отримав — щоб не нараховувати вдруге
        // при перерахунку минулих тижнів.
        Schema::create('user_investment_achievements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('investment_achievement_tiers')->cascadeOnDelete();
            $table->timestamp('earned_at');
            $table->timestamps();

            $table->unique(['user_id', 'tier_id']);
        });

        // Єдиний рядок конфігурації створюємо одразу — контролер завжди
        // очікує, що він є (first(), а не firstOrCreate() на кожен запит).
        DB::table('bonus_settings')->insert(['created_at' => now(), 'updated_at' => now()]);

        $permission = Permission::firstOrCreate(['name' => 'bonuses.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('user_investment_achievements');
        Schema::dropIfExists('bonus_payouts');
        Schema::dropIfExists('investment_achievement_tiers');
        Schema::dropIfExists('bonus_settings');
        Permission::where('name', 'bonuses.manage')->delete();
    }
};
