<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['core', 'module', 'plugin', 'theme']);
            $table->string('slug'); // машинное имя, напр. "family-goals-activity"
            $table->string('name'); // человекочитаемое, напр. "Family Goals & Activity"
            $table->string('version'); // semver "1.2.0"
            $table->json('manifest'); // манифест как загружен, для аудита/отладки
            $table->enum('status', ['active', 'inactive', 'pending_migration', 'failed'])
                ->default('inactive');
            $table->string('path'); // относительный путь в storage/app/addons/...
            $table->boolean('migrations_applied')->default(false);
            $table->foreignId('installed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('last_error')->nullable();
            $table->timestamps();

            // один и тот же slug может существовать в нескольких версиях подряд
            // (обновление не удаляет предыдущую сразу), но активна — только одна.
            $table->unique(['type', 'slug', 'version']);
            $table->index(['type', 'slug', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addons');
    }
};
