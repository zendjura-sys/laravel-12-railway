<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            // S/A/B/C/D/F/G — виставляється разом із затвердженням, тому
            // nullable: старі звіти й ще не розглянуті лишаються без оцінки.
            $table->string('grade', 1)->nullable()->after('review_note');
            $table->text('grade_reason')->nullable()->after('grade');
        });
    }

    public function down(): void
    {
        Schema::table('reports', function (Blueprint $table) {
            $table->dropColumn(['grade', 'grade_reason']);
        });
    }
};
