<?php

namespace Addons\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BroadcastDelivery extends Model
{
    protected $table = 'broadcast_deliveries';

    protected $fillable = ['broadcast_id', 'user_id', 'status', 'attempts', 'last_error', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
