<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Все зашифроване (не hashed): secret і recovery-коди мають
            // читатись назад — TOTP звіряється проти живого секрету, а
            // використаний recovery-код видаляється зі списку, а не просто
            // звіряється хешем.
            $table->text('two_factor_secret')->nullable()->after('remember_token');
            $table->text('two_factor_recovery_codes')->nullable()->after('two_factor_secret');
            // Секрет пишеться одразу при "Увімкнути", а підтвердженим
            // вважається лише після першого вірно введеного коду — інакше
            // хтось, хто зайшов на сторінку налаштувань і вийшов, лишав би
            // собі активний секрет без жодної гарантії, що зберіг його в
            // застосунку-аутентифікаторі.
            $table->timestamp('two_factor_confirmed_at')->nullable()->after('two_factor_recovery_codes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at']);
        });
    }
};
