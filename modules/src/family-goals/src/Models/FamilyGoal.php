<?php

namespace Addons\FamilyGoals\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyGoal extends Model
{
    protected $table = 'family_goals';

    protected $fillable = [
        'title', 'description', 'target_value', 'current_value',
        'unit', 'deadline', 'status', 'created_by',
    ];

    protected $attributes = [
        'current_value' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return ['deadline' => 'date'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function progressPercent(): ?int
    {
        if (! $this->target_value) {
            return null;
        }

        return (int) min(100, round($this->current_value / $this->target_value * 100));
    }
}
