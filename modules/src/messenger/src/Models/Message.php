<?php

namespace Addons\Messenger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'sender_id', 'reply_to_message_id', 'body', 'type', 'attachment_path', 'attachment_url',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    /**
     * URL вкладення незалежно від типу: photo/sticker зберігають лише
     * шлях (attachment_path, приватний диск public), gif — готове зовнішнє
     * посилання Giphy (attachment_url).
     */
    protected function attachmentUrl(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value) {
                    return $value;
                }

                return $this->attachment_path ? Storage::disk('public')->url($this->attachment_path) : null;
            },
        );
    }
}
