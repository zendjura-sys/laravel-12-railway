<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UnionComplaint extends Model
{
    protected $fillable = [
        'reporter_id', 'against_family', 'against_name', 'reasons',
        'description', 'status', 'reviewed_by', 'reviewer_note', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'reasons' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public const STATUSES = ['pending', 'in_review', 'resolved', 'dismissed'];

    /**
     * Причини скарги — рп-терміни ГТА рп-серверів з поясненням, множинний
     * вибір у формі. Ключі стабільні (як User::UNION_ROLES) — на них
     * зав'язане поле reasons у БД, текст можна міняти без міграції даних.
     */
    public const REASONS = [
        'rdm' => [
            'label' => 'RDM (Random Death Match)',
            'description' => 'Убивство персонажа без ігрової причини чи рольового приводу.',
        ],
        'vdm' => [
            'label' => 'VDM (Vehicle Death Match)',
            'description' => 'Навмисний наїзд/убивство транспортом без рольової причини.',
        ],
        'powergaming' => [
            'label' => 'Powergaming',
            'description' => 'Дії, які фізично неможливі або нав\'язують результат іншому гравцю без шансу відреагувати.',
        ],
        'metagaming' => [
            'label' => 'Metagaming',
            'description' => 'Використання інформації, отриманої поза грою (Discord, стрім), у рольовій ситуації.',
        ],
        'failrp' => [
            'label' => 'FailRP',
            'description' => 'Поведінка, що суперечить здоровому глузду й духу рольової гри.',
        ],
        'nvl' => [
            'label' => 'NVL (New Life Rule)',
            'description' => 'Порушення правила "нового життя" — повернення до попередньої ситуації після смерті персонажа.',
        ],
        'combat_logging' => [
            'label' => 'Combat logging',
            'description' => 'Вихід із гри чи сервера під час активної рольової/бойової ситуації, щоб уникнути наслідків.',
        ],
        'fearrp' => [
            'label' => 'Fear RP',
            'description' => 'Ігнорування страху за життя персонажа там, де рольова ситуація цього вимагає.',
        ],
        'scamming' => [
            'label' => 'Обман домовленостей',
            'description' => 'Порушення ігрової чи позаігрової домовленості між родинами/учасниками.',
        ],
        'disrespect' => [
            'label' => 'Неповага/токсичність',
            'description' => 'Образи, цькування чи неповажне спілкування поза межами рольової ситуації.',
        ],
        'cheating' => [
            'label' => 'Читерство/баги',
            'description' => 'Використання стороннього ПЗ, експлойтів чи багів сервера для переваги.',
        ],
        'other' => [
            'label' => 'Інше',
            'description' => 'Причина, що не підпадає під жоден із перелічених термінів — деталі в описі.',
        ],
    ];

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(UnionComplaintAttachment::class);
    }

    /**
     * Лідер/заступник обвинуваченої родини бачить скарги проти СВОЄЇ
     * родини (за union_family_name, без урахування регістру — люди
     * вводять назву по-різному), Monsory-адмін (union.manage) — усі.
     */
    public static function visibleTo(User $user): \Illuminate\Database\Eloquent\Builder
    {
        $query = self::query();

        if ($user->can('union.manage')) {
            return $query;
        }

        if ($user->union_family_name && in_array($user->union_role, ['leader', 'deputy'], true)) {
            return $query->whereRaw('LOWER(against_family) = ?', [mb_strtolower($user->union_family_name)]);
        }

        return $query->whereRaw('1 = 0');
    }
}
