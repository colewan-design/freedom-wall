<?php

namespace App\Console\Commands;

use App\Mail\JobDigestMail;
use App\Models\JobMonitor;
use App\Models\JobMonitorHit;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendJobDigests extends Command
{
    protected $signature = 'jobs:digest';

    protected $description = 'Email each due JobRadar monitor the matches it has not sent yet';

    /** A digest is a summary; past this the page is the better place to look. */
    private const MAX_PER_DIGEST = 15;

    public function handle(): int
    {
        $sent = 0;

        JobMonitor::query()
            ->active()
            ->where('email_alerts', true)
            ->with('user:id,name,email,email_verified_at')
            ->each(function (JobMonitor $monitor) use (&$sent) {
                if (! $monitor->digestIsDue() || ! $monitor->user?->hasVerifiedEmail()) {
                    return;
                }

                $hits = $monitor->hits()
                    ->whereNull('emailed_at')
                    ->whereHas('listing', fn ($query) => $query->approved())
                    ->with('listing')
                    ->orderByDesc('score')
                    ->get();

                // Nothing new is not worth an email, and not worth resetting
                // the clock either — the next match should go out promptly.
                if ($hits->isEmpty()) {
                    return;
                }

                try {
                    Mail::to($monitor->user)->send(new JobDigestMail($monitor, $hits->take(self::MAX_PER_DIGEST), $hits->count()));
                } catch (Throwable $e) {
                    Log::warning('JobRadar digest failed', ['monitor' => $monitor->id, 'error' => $e->getMessage()]);

                    return;
                }

                JobMonitorHit::query()->whereKey($hits->modelKeys())->update(['emailed_at' => now()]);
                $monitor->forceFill(['last_digest_at' => now()])->save();
                $sent++;
            });

        $this->info("Sent {$sent} digest(s).");

        return self::SUCCESS;
    }
}
