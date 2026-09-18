<?php

namespace App\Models;

use App\Support\FamilyContent;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        // Только контролируемые пути пишут его явным литералом
        // (регистрация — 'trainee', адмінка — через провалідований
        // UserController). Ни один путь не берёт значение прямо из
        // $request-массива.
        'position_key',
        'is_shadow',
        'birth_date',
        'gender',
        'avatar_path',
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
     * position_title вычисляемый — расшаривается вместе с моделью везде,
     * где она уже сериализуется (Inertia auth.user, список учасників в
     * адмінці), без ручного докидывания в каждый контроллер.
     *
     * @var list<string>
     */
    protected $appends = [
        'position_title',
        'avatar_url',
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
            'is_shadow' => 'boolean',
            'birth_date' => 'date',
        ];
    }

    /**
     * Дієслово у правильному роді для системних текстів ("подав"/"подала",
     * "створив"/"створила" тощо). gender не вказано (null) — лишається
     * чоловіча форма, той самий текст, що показувався до появи цього поля.
     */
    public function verb(string $masculine, string $feminine): string
    {
        return $this->gender === 'f' ? $feminine : $masculine;
    }

    /**
     * Заводить акаунт "за друга" — звіт мусить мати реальний user_id
     * (усі зв'язки в Progression/Bonuses/Notifications зав'язані на FK),
     * тому це не текстова заглушка, а справжній рядок users. Email/пароль —
     * непридатний плейсхолдер: увійти цим ніхто не зможе, аж поки сама
     * людина не зареєструється й не забере акаунт (RegisteredUserController).
     */
    public static function createShadow(string $firstName, ?string $lastName): self
    {
        return self::create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => 'shadow-'.Str::random(20).'@monsory.invalid',
            'password' => Str::random(40),
            'is_shadow' => true,
            'position_key' => FamilyContent::positionKeys()[0] ?? null,
        ]);
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

    /**
     * Посада в родині — окрема річ від ролей доступу (admin/тощо, тут ні
     * до чого) і від XP у модулі Progression (той окремий ігровий бал, а
     * не офіційна посада). Єдине джерело правди — config/family.php через
     * FamilyContent, те саме, що читають сайт і бот.
     *
     * Зберігається СТАБІЛЬНИМ КЛЮЧЕМ (position_key), а не індексом:
     * посади в Дизайн → Розділи можна переставляти й видаляти, і при
     * зберіганні індексом перестановка мовчки переприсвоювала б людям
     * чужі посади.
     */
    protected function positionTitle(): Attribute
    {
        return Attribute::make(
            get: fn () => FamilyContent::positionByKey($this->position_key)['title'] ?? null,
        );
    }

    /**
     * Публічне посилання на фото профілю — обчислюване, як і position_title,
     * щоб карусель учасників на головній не робила N окремих перевірок
     * файлу в storage.
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->avatar_path && Storage::disk('public')->exists($this->avatar_path)
                ? Storage::disk('public')->url($this->avatar_path)
                : null,
        );
    }
}
