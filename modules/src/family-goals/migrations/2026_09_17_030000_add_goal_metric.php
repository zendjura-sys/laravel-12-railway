<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // null = як і раніше, вручну через "Оновити прогрес" в адмінці.
        // Заповнене значення — ціль сама рухається від затверджених звітів,
        // без участі адміна. Ключі співпадають з тим, що вже рахує Progression
        // (reports_total/investment_total/kapt_wins/contracts_count) — той же
        // сенс, але зовсім окремий підрахунок: цілі не залежать від модуля
        // Progression і рахують безпосередньо зі звітів.
        Schema::table('family_goals', function (Blueprint $table) {
            $table->string('metric')->nullable()->after('unit');
        });
    }

    public function down(): void
    {
        Schema::table('family_goals', function (Blueprint $table) {
            $table->dropColumn('metric');
        });
    }
};
