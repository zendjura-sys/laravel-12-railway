<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Заведений кимось "за друга" акаунт без email/пароля —
            // не може увійти, поки сама людина не зареєструється і не
            // забере його (RegisteredUserController). email/password
            // лишаються заповненими плейсхолдером (унікальний email
            // колонка вимагає NOT NULL), просто ним ніхто не входить.
            $table->boolean('is_shadow')->default(false)->after('position_key');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_shadow');
        });
    }
};
