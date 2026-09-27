<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('changelog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            // Окремо від created_at: адмін мусить мати змогу вписати
            // заднім числом (додав запис за минулий тиждень) — час
            // публікації визначає стрічку і сортування, а не коли рядок
            // фізично з'явився в БД.
            $table->date('published_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('changelog_entries');
    }
};
