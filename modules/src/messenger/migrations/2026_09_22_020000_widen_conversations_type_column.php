<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Було enum('family','direct') — CHECK-обмеження в SQLite не
        // пускало новий тип 'deputies' (чат заступників). Той самий
        // прийом, що й для addons.type: переводимо на звичайний string,
        // дозволені значення контролює MessengerController у коді.
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('type', 20)->default('direct')->change();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->enum('type', ['family', 'direct'])->default('direct')->change();
        });
    }
};
