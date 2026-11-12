<?php

namespace App\Mail;

use App\Models\JobMonitor;
use App\Models\JobMonitorHit;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class JobDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param Collection<int, JobMonitorHit> $hits */
    public function __construct(
        public JobMonitor $monitor,
        public Collection $hits,
        public int $total,
    ) {}

    public function envelope(): Envelope
    {
        $count = $this->total === 1 ? '1 new job' : "{$this->total} new jobs";

        return new Envelope(subject: "{$count} for “{$this->monitor->name}”");
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.job-digest', with: [
            'monitor' => $this->monitor,
            'hits' => $this->hits,
            'total' => $this->total,
            'url' => route('jobs.mine'),
        ]);
    }
}
