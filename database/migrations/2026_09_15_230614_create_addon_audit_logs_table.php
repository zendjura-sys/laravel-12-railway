<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addon_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->nullable()->constrained('addons')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // installed | activated | deactivated | uninstalled | migrations_applied | failed
            $table->string('action');
            $table->json('meta')->nullable();
            $table->string('ip')->nullable();
            $table->timestamps();

            $table->index(['addon_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addon_audit_logs');
    }
};
