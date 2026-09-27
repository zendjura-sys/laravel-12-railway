<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Скарга подається зареєстрованим союзником на когось із іншої
        // родини союзу. against_family — вільний текст, звірений при вводі
        // з автодоповненням проти users.union_family_name (щоб не було
        // одної родини під п'ятьма написаннями), against_name — нік
        // обвинуваченого вільним текстом (може не мати акаунту взагалі).
        Schema::create('union_complaints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $table->string('against_family');
            $table->string('against_name');
            // Масив ключів із UnionComplaint::REASONS — множинний вибір.
            $table->json('reasons');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reviewer_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            // Пошук скарг проти конкретної родини — лідер/заступник бачить
            // лише те, що стосується його власної родини.
            $table->index('against_family');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_complaints');
    }
};
