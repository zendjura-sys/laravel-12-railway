<?php

namespace App\Support;

use App\Models\User;

/**
 * "Картка учасника" — візуальний елемент сторінки "Банк": псевдо-
 * випадковий 16-значний номер (як на справжніх картках — рівно 4 групи
 * по 4 символи), унікальний для користувача й СТАБІЛЬНИЙ (той самий при
 * кожному відкритті сторінки). Виводиться детерміновано з id + APP_KEY,
 * тому не потребує ні нового стовпця в users, ні міграції — той самий
 * підхід, що й позиції з key замість збереження зайвого стану.
 */
class MemberCard
{
    public static function number(User $user): string
    {
        $hash = hash('sha256', config('app.key').'|member-card|'.$user->id);
        $decimal = base_convert(substr($hash, 0, 16), 16, 10);

        return str_pad(substr($decimal, -16), 16, '0', STR_PAD_LEFT);
    }

    /** Приховано все, крім останніх 4 цифр — 12 зірочок попереду. */
    public static function masked(User $user): string
    {
        return str_repeat('*', 12).substr(self::number($user), -4);
    }
}
