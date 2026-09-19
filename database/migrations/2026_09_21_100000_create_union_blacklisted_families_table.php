<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ЧСС (чорний список союзу) для родин — тимчасовий бан: родина з
        // таким union_family_name не може зареєструватись, поки expires_at
        // не мине. duration_hours зберігається окремо від expires_at, щоб
        // адмінка при редагуванні показувала введену тривалість, а не
        // перераховувала її назад з дати.
        Schema::create('union_blacklisted_families', function (Blueprint $table) {
            $table->id();
            $table->string('family_name');
            $table->text('reason');
            $table->unsignedInteger('duration_hours');
            $table->timestamp('expires_at');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('family_name');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_blacklisted_families');
    }
};
