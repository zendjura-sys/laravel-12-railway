<?php

namespace Addons\MemberCenter\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberWarning extends Model
{
    protected $table = 'member_warnings';

    protected $fillable = ['user_id', 'author_id', 'severity', 'reason'];

    public const SEVERITIES = ['notice', 'warning', 'severe'];

    public const SEVERITY_LABELS = [
        'notice' => 'Зауваження',
        'warning' => 'Попередження',
        'severe' => 'Сувора догана',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
