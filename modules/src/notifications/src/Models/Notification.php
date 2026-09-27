<?php

namespace Addons\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'url', 'read_at'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function notify(int $userId, string $type, string $title, ?string $body = null, ?string $url = null): self
    {
        return self::create(['user_id' => $userId, 'type' => $type, 'title' => $title, 'body' => $body, 'url' => $url]);
    }
}
