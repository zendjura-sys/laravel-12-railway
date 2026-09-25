<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_audit_logs', function (Blueprint $table) {
            $table->id();
            // Хто натиснув кнопку. nullOnDelete — щоб видалення адміна не
            // забирало разом з собою історію дій, які він устиг зробити.
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            // Кого стосується дія. Теж nullOnDelete: після destroy() сам
            // акаунт зникає, але запис лишається — ім'я/email на той
            // момент лежать у meta, а не лише у зв'язку.
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            // roles_updated | position_updated | profile_updated |
            // password_reset | account_deleted
            $table->string('action');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_audit_logs');
    }
};
