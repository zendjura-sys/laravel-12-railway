<?php

namespace Addons\Reports\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ReportAttachment extends Model
{
    protected $table = 'report_attachments';

    protected $fillable = ['report_id', 'disk_path', 'original_name', 'size', 'position'];

    /** disk_path — внутрішній шлях на диску, фронту потрібен лише готовий url. */
    protected $hidden = ['disk_path', 'report_id', 'updated_at'];

    protected $appends = ['url'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk('public')->url($this->disk_path));
    }
}
