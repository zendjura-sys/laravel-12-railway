<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Публічний X25519-ключ для наскрізного шифрування особистих
        // чатів (Фаза 1: лише мобільний-мобільний, direct-розмови).
        // Приватний ключ ніколи не покидає пристрій (flutter_secure_storage) —
        // тут лежить рівно те, що й мало бути публічним. Один рядок на
        // користувача: перевстановлення застосунку чи новий пристрій
        // перезаписує ключ (updateOrCreate), старі зашифровані повідомлення
        // стають нечитабельними — свідомий компроміс Фази 1, без окремого
        // бекапу ключів.
        Schema::create('user_identity_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('public_key');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_identity_keys');
    }
};
