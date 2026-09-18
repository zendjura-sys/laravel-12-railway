<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('family_events', function (Blueprint $table) {
            // Окремий прапорець від reminder_sent_at (за день до події) —
            // це друге, незалежне нагадування "скоро починається", і крон,
            // що його шле, ганяється щохвилини, а не раз на добу.
            $table->timestamp('soon_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('family_events', function (Blueprint $table) {
            $table->dropColumn('soon_reminder_sent_at');
        });
    }
};
