<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JobListing extends Model
{
    public const EMPLOYMENT_TYPES = ['full-time', 'part-time', 'contractual', 'freelance', 'internship'];

    public const SECTORS = ['government', 'private'];

    public const MAX_TITLE_LENGTH = 200;

    public const MAX_DESCRIPTION_LENGTH = 8000;

    protected $fillable = [
        'title', 'employer', 'location', 'is_remote', 'employment_type', 'sector',
        'salary_text', 'description', 'apply_url', 'deadline', 'published_at',
        'discovered_at', 'status', 'fingerprint', 'safety_flags',
        'submitted_by_user_id', 'submitter_ip_hash',
    ];

    protected $hidden = ['submitter_ip_hash', 'submitted_by_user_id', 'fingerprint'];

    protected $casts = [
        'is_remote' => 'boolean',
        'deadline' => 'date',
        'published_at' => 'datetime',
        'discovered_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'safety_flags' => 'array',
    ];

    public function sources(): HasMany
    {
        return $this->hasMany(JobListingSource::class);
    }

    public function saves(): HasMany
    {
        return $this->hasMany(JobSave::class);
    }

    public function scopeApproved(Builder $query): void
    {
        $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): void
    {
        $query->where('status', 'pending');
    }

    /** Freshest first — by when we found it, since posting dates are patchy. */
    public function scopeLatestDiscovered(Builder $query): void
    {
        $query->orderByDesc('discovered_at')->orderByDesc('id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * The fields a listing is missing, so the page can say "not stated" rather
     * than leave a gap that reads like an oversight — or worse, guess.
     *
     * @return list<string>
     */
    public function missingFields(): array
    {
        return array_keys(array_filter([
            'employer' => blank($this->employer),
            'location' => blank($this->location) && ! $this->is_remote,
            'salary' => blank($this->salary_text),
            'employment_type' => blank($this->employment_type),
            'deadline' => $this->deadline === null,
            'description' => blank($this->description),
        ]));
    }
}
