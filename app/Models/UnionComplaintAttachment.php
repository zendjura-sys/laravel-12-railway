<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class UnionComplaintAttachment extends Model
{
    protected $fillable = ['union_complaint_id', 'disk_path', 'original_name', 'size', 'position'];

    protected $hidden = ['disk_path', 'union_complaint_id', 'updated_at'];

    protected $appends = ['url'];

    public function complaint(): BelongsTo
    {
        return $this->belongsTo(UnionComplaint::class, 'union_complaint_id');
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->disk_path));
    }
}
