<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A bookmarked job, which is also where its application progress lives. */
class JobSave extends Model
{
    public const STATUSES = [
        'interested' => 'Interested',
        'preparing' => 'Preparing requirements',
        'applied' => 'Applied',
        'under-review' => 'Under review',
        'interview' => 'Interview scheduled',
        'offered' => 'Offered',
        'hired' => 'Hired',
        'rejected' => 'Rejected',
        'closed' => 'Closed',
    ];

    protected $fillable = ['user_id', 'job_listing_id', 'status', 'notes', 'applied_on', 'follow_up_on'];

    protected $casts = [
        'applied_on' => 'date:Y-m-d',
        'follow_up_on' => 'date:Y-m-d',
    ];

    public function listing(): BelongsTo
    {
        return $this->belongsTo(JobListing::class, 'job_listing_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
