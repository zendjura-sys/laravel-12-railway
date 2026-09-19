<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Новини/оголошення для стрічки в кабінеті союзника — веде адмін
        // (Союз → Оголошення), читають усі зареєстровані союзники.
        Schema::create('union_announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('body');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->useCurrent();
            $table->timestamps();

            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_announcements');
    }
};
