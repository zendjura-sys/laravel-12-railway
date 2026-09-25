<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // report_id навмисно без ->constrained(): ai-assistant не має
        // жорсткої залежності від Reports (може бути встановлений і без
        // нього), той самий приём розв'язки модулів, що й скрізь у проєкті.
        Schema::create('ai_report_reviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('report_id')->unique();
            $table->boolean('flagged')->default(false);
            $table->boolean('date_mismatch')->default(false);
            $table->text('date_mismatch_detail')->nullable();
            $table->boolean('count_mismatch')->default(false);
            $table->text('count_mismatch_detail')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_report_reviews');
    }
};
