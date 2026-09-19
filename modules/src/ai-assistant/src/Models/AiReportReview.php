<?php

namespace Addons\AiAssistant\Models;

use Illuminate\Database\Eloquent\Model;

class AiReportReview extends Model
{
    protected $table = 'ai_report_reviews';

    protected $fillable = [
        'report_id', 'flagged', 'date_mismatch', 'date_mismatch_detail',
        'count_mismatch', 'count_mismatch_detail', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'flagged' => 'boolean',
            'date_mismatch' => 'boolean',
            'count_mismatch' => 'boolean',
        ];
    }
}
