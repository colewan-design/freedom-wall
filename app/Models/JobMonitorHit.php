<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobMonitorHit extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['job_monitor_id', 'job_listing_id', 'score', 'reasons', 'seen_at', 'emailed_at'];

    protected $casts = [
        'reasons' => 'array',
        'score' => 'integer',
        'seen_at' => 'datetime',
        'emailed_at' => 'datetime',
    ];

    public function monitor(): BelongsTo
    {
        return $this->belongsTo(JobMonitor::class, 'job_monitor_id');
    }

    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }
}
