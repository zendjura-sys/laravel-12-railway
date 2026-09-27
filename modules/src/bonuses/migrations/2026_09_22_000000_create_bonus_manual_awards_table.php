<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ручна премія — окремо від bonus_payouts: той рахується
        // автоматично по тижнях (унікальний user_id+week_start,
        // розбивка по бізвару/контрактах), тут — разова сума "просто
        // так" без прив'язки до тижня чи автоматичного розрахунку.
        Schema::create('bonus_manual_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('note')->nullable();
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bonus_manual_awards');
    }
};
