<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChangelogEntry extends Model
{
    protected $fillable = ['title', 'description', 'published_at', 'created_by'];

    protected function casts(): array
    {
        return ['published_at' => 'date'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
