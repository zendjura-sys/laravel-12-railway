<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Кожен депозит "заморожує" суму з балансу на строк, знятий на
        // момент відкриття зі settings (відсоток/строк далі не змінюються
        // заднім числом, навіть якщо адмін поміняє налаштування) — і сам
        // рядок лишається назавжди як аудит-слід (той самий підхід, що
        // й у bank_transfers), навіть після закриття.
        Schema::create('bank_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->decimal('interest_rate', 5, 2);
            $table->unsignedInteger('term_days');
            $table->timestamp('matures_at');
            // active | completed (дозрів, з відсотком) | withdrawn (знято достроково, без відсотка)
            $table->string('status')->default('active');
            $table->unsignedBigInteger('payout_amount')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_deposits');
    }
};
