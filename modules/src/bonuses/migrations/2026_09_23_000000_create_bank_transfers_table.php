<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Переказ "картка-картка" між учасниками — окрема, від'ємна/додатня
        // частина балансу (Addons\Bonuses\Services\BalanceCalculator),
        // а не сама премія: сума йде ЗІ спільного банку нарахувань
        // відправника ДО отримувача, загальна сума премій родини не росте.
        Schema::create('bank_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('amount');
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('from_user_id');
            $table->index('to_user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_transfers');
    }
};
