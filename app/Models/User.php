<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * name — производная от имени и фамилии, а не отдельное поле.
     *
     * На неё завязано всё отображение (приветствие в кабинете, списки
     * участников, подписи в Telegram-боте, письма Laravel), поэтому
     * колонка осталась, но пересобирается здесь. Иначе после правки
     * фамилии в профиле в шапке сайта ещё неделю висело бы старое имя.
     */
    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! $user->isDirty(['first_name', 'last_name'])) {
                return;
            }

            $full = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));

            // Пустым name не перетираем: у аккаунтов, заведённых до
            // разделения полей, фамилии может не быть вовсе.
            if ($full !== '') {
                $user->name = $full;
            }
        });
    }

    public function fullName(): string
    {
        $full = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $full !== '' ? $full : (string) $this->name;
    }
}
