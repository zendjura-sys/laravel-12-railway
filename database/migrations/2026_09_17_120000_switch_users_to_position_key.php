<?php

use App\Support\FamilyContent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.position_index → users.position_key.
 *
 * Індекс у масиві виявився небезпечним вибором: у Дизайн → Розділи посади
 * можна переставляти стрілками ↑/↓ і видаляти — це звичайна, очікувана
 * дія адміна. При зберіганні індексом перестановка рядка мовчки
 * переприсвоювала б реальним людям чужі посади, без жодної помилки чи
 * попередження. Ключ (config/family.php: 'key' у кожній посаді) не
 * прив'язаний ні до порядку, ні до тексту — переживає і перестановку, і
 * перейменування.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('position_key', 80)->nullable()->after('position_index');
        });

        // Бекфіл читає ЖИВИЙ список посад (config або вже збережені в
        // адмінці правки) у ТОМУ Ж порядку, яким користувався
        // position_index, — на момент цієї міграції порядок ще не
        // встигли поміняти, бо саме зберігання ключем це і запобігає
        // надалі.
        $keys = FamilyContent::positionKeys();

        foreach (DB::table('users')->whereNotNull('position_index')->select('id', 'position_index')->get() as $user) {
            $key = $keys[$user->position_index] ?? null;

            if ($key !== null) {
                DB::table('users')->where('id', $user->id)->update(['position_key' => $key]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position_index');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('position_index')->nullable()->after('last_name');
        });

        $keys = FamilyContent::positionKeys();

        foreach (DB::table('users')->whereNotNull('position_key')->select('id', 'position_key')->get() as $user) {
            $index = array_search($user->position_key, $keys, true);

            if ($index !== false) {
                DB::table('users')->where('id', $user->id)->update(['position_index' => $index]);
            }
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('position_key');
        });
    }
};
