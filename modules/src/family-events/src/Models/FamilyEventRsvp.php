<?php

namespace Addons\FamilyEvents\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyEventRsvp extends Model
{
    protected $table = 'family_event_rsvps';

    protected $fillable = ['family_event_id', 'user_id', 'status'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(FamilyEvent::class, 'family_event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
