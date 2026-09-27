<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * member_warnings → повна система покарань за правилами родини (розділ 8):
     * зауваження, штраф, догана, пониження, виключення, чорний список — зі
     * строками дії, оплатою штрафів і автоматичною ескалацією.
     */
    public function up(): void
    {
        Schema::table('member_warnings', function (Blueprint $table) {
            $table->string('type')->default('remark')->after('author_id');
            $table->string('rule_code', 20)->nullable()->after('type');
            $table->unsignedInteger('amount')->nullable()->after('reason');
            $table->unsignedInteger('original_amount')->nullable()->after('amount');
            $table->string('status')->default('active')->after('original_amount');
            $table->timestamp('due_at')->nullable()->after('status');
            $table->timestamp('expires_at')->nullable()->after('due_at');
            $table->timestamp('paid_at')->nullable()->after('expires_at');
            $table->string('paid_via', 16)->nullable()->after('paid_at');
            $table->foreignId('resolved_by')->nullable()->after('paid_via')->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable()->after('resolved_by');
            $table->string('resolution_note', 500)->nullable()->after('resolved_at');
            $table->boolean('auto')->default(false)->after('resolution_note');
            $table->foreignId('source_id')->nullable()->after('auto')->constrained('member_warnings')->nullOnDelete();

            $table->index(['user_id', 'type', 'status']);
        });

        Schema::table('member_warnings', function (Blueprint $table) {
            $table->string('severity')->nullable()->change();
        });

        // Старі три рівні: «Зауваження»/«Попередження» → зауваження,
        // «Сувора догана» → догана. Строк дії рахуємо від дати видачі —
        // старі записи, яким уже понад 7/14 днів, одразу стануть згорілими.
        DB::table('member_warnings')->whereIn('severity', ['notice', 'warning'])->update(['type' => 'remark']);
        DB::table('member_warnings')->where('severity', 'severe')->update(['type' => 'reprimand']);
        foreach (DB::table('member_warnings')->get(['id', 'type', 'created_at']) as $row) {
            $days = $row->type === 'reprimand' ? 14 : 7;
            $expires = \Illuminate\Support\Carbon::parse($row->created_at)->addDays($days);
            DB::table('member_warnings')->where('id', $row->id)->update([
                'expires_at' => $expires,
                'status' => $expires->isPast() ? 'expired' : 'active',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('member_warnings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_id');
            $table->dropConstrainedForeignId('resolved_by');
            $table->dropIndex(['user_id', 'type', 'status']);
            $table->dropColumn([
                'type', 'rule_code', 'amount', 'original_amount', 'status', 'due_at', 'expires_at',
                'paid_at', 'paid_via', 'resolved_at', 'resolution_note', 'auto',
            ]);
        });
    }
};
