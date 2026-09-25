<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        // Один рядок на учасника — hr_status має DB-дефолт 'active', тож
        // навіть без явного профілю (перед першим зверненням HR) кожен
        // учасник вважається активним, а не "невідомим".
        Schema::create('member_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('hr_status')->default('active');
            $table->timestamps();
        });

        // Приватні нотатки HR про учасника — бачить лише той, хто веде
        // кадровий облік (members.manage), ніколи сам учасник.
        Schema::create('member_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->text('reason')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Модуль сам заводить собі право і видає його ролі admin — той
        // самий підхід, що в Reports/Progression.
        $permission = Permission::firstOrCreate(['name' => 'members.manage', 'guard_name' => 'web']);
        $adminRole = Role::where('name', 'admin')->where('guard_name', 'web')->first();
        $adminRole?->givePermissionTo($permission);
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
        Schema::dropIfExists('member_notes');
        Schema::dropIfExists('member_profiles');
        Permission::where('name', 'members.manage')->delete();
    }
};
