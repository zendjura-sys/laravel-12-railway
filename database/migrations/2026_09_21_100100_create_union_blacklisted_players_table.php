<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ЧС гравця — безстроковий (на відміну від родини), додати може
        // будь-який зареєстрований союзник, не лише адмін. family_name —
        // вільний текст, за бажанням підтягнутий з автодоповнення проти
        // union_blacklisted_families/users.union_family_name.
        Schema::create('union_blacklisted_players', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('family_name')->nullable();
            // Масив ключів із UnionComplaint::REASONS — той самий список
            // рп-термінів, що й у скаргах, множинний вибір.
            $table->json('reasons');
            $table->text('description')->nullable();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['first_name', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_blacklisted_players');
    }
};
