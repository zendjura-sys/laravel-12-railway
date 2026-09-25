<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('telegram_applications', function (Blueprint $table) {
            // Ссылка одноразовая и с истечением, поэтому храним выданную:
            // иначе на повторный заход пришлось бы плодить новые, а старые
            // так и висели бы действующими.
            $table->string('invite_link', 255)->nullable()->after('review_note');
            $table->timestamp('joined_at')->nullable()->after('invite_link');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_applications', function (Blueprint $table) {
            $table->dropColumn(['invite_link', 'joined_at']);
        });
    }
};
