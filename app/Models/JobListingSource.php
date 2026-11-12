<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobListingSource extends Model
{
    public $timestamps = false;

    protected $fillable = ['job_listing_id', 'job_source_id', 'external_id', 'url', 'label', 'first_seen_at', 'last_seen_at'];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
    }
}
