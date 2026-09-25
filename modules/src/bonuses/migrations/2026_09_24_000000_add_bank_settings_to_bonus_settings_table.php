<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bonus_settings', function (Blueprint $table) {
            // Перекази картка-картка
            $table->boolean('transfer_enabled')->default(true);
            // NULL = без ліміту.
            $table->unsignedBigInteger('transfer_daily_limit')->nullable();
            $table->unsignedBigInteger('transfer_min_amount')->default(1);

            // Депозити
            $table->boolean('deposit_enabled')->default(true);
            // Відсоток за весь строк депозиту (не річних), напр. 5.00 = +5%.
            $table->decimal('deposit_interest_rate', 5, 2)->default(5.00);
            $table->unsignedBigInteger('deposit_min_amount')->default(100);
            $table->unsignedInteger('deposit_term_days')->default(30);
        });
    }

    public function down(): void
    {
        Schema::table('bonus_settings', function (Blueprint $table) {
            $table->dropColumn([
                'transfer_enabled', 'transfer_daily_limit', 'transfer_min_amount',
                'deposit_enabled', 'deposit_interest_rate', 'deposit_min_amount', 'deposit_term_days',
            ]);
        });
    }
};
