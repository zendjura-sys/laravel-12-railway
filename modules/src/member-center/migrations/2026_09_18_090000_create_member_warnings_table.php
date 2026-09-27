<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // На відміну від member_notes (приватні, бачить лише HR),
        // попередження — офіційна дія: учасника сповіщають про нього
        // (web + Telegram), тому severity й reason обов'язкові.
        Schema::create('member_warnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('severity');
            $table->text('reason');
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('member_warnings');
    }
};
