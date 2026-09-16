<?php

namespace Addons\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Broadcast extends Model
{
    protected $table = 'broadcasts';

    protected $fillable = ['title', 'body', 'pinned', 'created_by'];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
