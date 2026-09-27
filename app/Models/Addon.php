<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Addon extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', 'slug', 'name', 'version', 'manifest', 'status',
        'path', 'migrations_applied', 'installed_by', 'last_error',
    ];

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'migrations_applied' => 'boolean',
        ];
    }

    public function installer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'installed_by');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AddonAuditLog::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
