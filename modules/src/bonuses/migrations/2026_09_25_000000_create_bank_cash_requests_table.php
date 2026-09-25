<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Запит на видачу готівки "на руки" — сайт лише фіксує запит і
        // сповіщає керівництво (bonuses.manage), сама передача грошей
        // відбувається поза сайтом. pending одразу заморожує суму (як і
        // депозит), щоб її не можна було витратити ще раз, поки керівництво
        // не підтвердить видачу чи не скасує запит.
        Schema::create('bank_cash_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            // pending | completed (видано на руки) | cancelled (скасовано, кошти повернуто)
            $table->string('status')->default('pending');
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_cash_requests');
    }
};
