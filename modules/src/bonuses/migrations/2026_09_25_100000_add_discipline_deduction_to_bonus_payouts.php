<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Утримання з тижневої премії за активну догану (правила родини:
        // "поки діє догана — премія нараховується на 50%"). Окремою
        // колонкою, щоб у виписці було видно, звідки менша сума.
        Schema::table('bonus_payouts', function (Blueprint $table) {
            $table->unsignedInteger('discipline_deduction_amount')->default(0)->after('investment_bonus_amount');
        });
    }

    public function down(): void
    {
        Schema::table('bonus_payouts', function (Blueprint $table) {
            $table->dropColumn('discipline_deduction_amount');
        });
    }
};
