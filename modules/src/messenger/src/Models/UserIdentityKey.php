<?php

namespace Addons\Messenger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserIdentityKey extends Model
{
    protected $fillable = ['user_id', 'public_key'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
