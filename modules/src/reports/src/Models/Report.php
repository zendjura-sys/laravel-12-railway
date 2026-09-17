<?php

namespace Addons\Reports\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Report extends Model
{
    use HasFactory;

    protected $table = 'reports';

    /**
     * 'kapt' лишається тут заради історичних записів (типи звітів, подані
     * до появи бізвару) — але його більше нема у формі подачі, дивись
     * SUBMITTABLE_TYPES.
     */
    public const TYPES = ['kapt', 'contract', 'bizwar', 'investment', 'other'];

    /** Те, що зараз можна вибрати у формі подачі звіту. */
    public const SUBMITTABLE_TYPES = ['contract', 'bizwar', 'investment', 'other'];

    /** Часові слоти капта — погодинно, для майбутнього підрахунку премій. */
    public const KAPT_TIMES = [
        '10:00', '11:00', '12:00', '13:00', '14:00', '15:00', '16:00',
        '17:00', '18:00', '19:00', '20:00', '21:00', '22:00',
    ];

    protected $fillable = [
        'user_id', 'submitted_by', 'type', 'report_date',
        'outcome', 'wins_count', 'losses_count', 'kapt_times',
        'weight', 'light_count', 'medium_count', 'heavy_count',
        'amount', 'description', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
            'report_date' => 'date',
            'amount' => 'integer',
            'wins_count' => 'integer',
            'losses_count' => 'integer',
            'kapt_times' => 'array',
            'light_count' => 'integer',
            'medium_count' => 'integer',
            'heavy_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
