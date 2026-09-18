<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('family_event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('family_event_id')->constrained('family_events')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20); // 'going' | 'not_going'
            $table->timestamps();

            $table->unique(['family_event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_event_rsvps');
    }
};
