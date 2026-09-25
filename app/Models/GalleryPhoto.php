<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class GalleryPhoto extends Model
{
    protected $table = 'family_gallery_photos';

    protected $fillable = ['path', 'caption', 'uploaded_by', 'sort_order'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): ?string
    {
        return Storage::disk('public')->exists($this->path) ? Storage::disk('public')->url($this->path) : null;
    }
}
