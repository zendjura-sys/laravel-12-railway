<?php

namespace Addons\FamilyEvents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyEvent extends Model
{
    protected $table = 'family_events';

    protected $fillable = ['title', 'description', 'location', 'starts_at', 'created_by', 'reminder_sent_at'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isPast(): bool
    {
        return $this->starts_at->isPast();
    }
}
