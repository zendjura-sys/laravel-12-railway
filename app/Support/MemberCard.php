<?php

namespace App\Support;

use App\Models\User;

/**
 * "Картка учасника" — візуальний елемент сторінки "Мої премії": псевдо-
 * випадковий 14-значний номер, унікальний для користувача й СТАБІЛЬНИЙ
 * (той самий при кожному відкритті сторінки). Виводиться детерміновано з
 * id + APP_KEY, тому не потребує ні нового стовпця в users, ні міграції —
 * той самий підхід, що й позиції з key замість збереження зайвого стану.
 */
class MemberCard
{
    public static function number(User $user): string
    {
        $hash = hash('sha256', config('app.key').'|member-card|'.$user->id);
        $decimal = base_convert(substr($hash, 0, 14), 16, 10);

        return str_pad(substr($decimal, -14), 14, '0', STR_PAD_LEFT);
    }

    /** Приховано все, крім останніх 4 цифр — 10 зірочок попереду, як і просили. */
    public static function masked(User $user): string
    {
        return str_repeat('*', 10).substr(self::number($user), -4);
    }
}
