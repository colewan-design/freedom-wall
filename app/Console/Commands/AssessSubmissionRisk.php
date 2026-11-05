<?php

namespace App\Console\Commands;

use App\Models\Submission;
use App\Services\ContentRiskService;
use Illuminate\Console\Command;

/**
 * Scores submissions that predate the risk assessor, so the queue is useful on
 * the day this ships rather than only for posts written after it.
 *
 * Safe to re-run: it only touches rows that have never been assessed, unless
 * --all is passed, which is what you want after editing the term lists in
 * config/moderation.php and wanting the existing queue rescored against them.
 */
class AssessSubmissionRisk extends Command
{
    protected $signature = 'wall:assess-risk
                            {--all : Rescore every submission, not just the unassessed ones}
                            {--status= : Limit to one status (pending, approved, rejected)}';

    protected $description = 'Score existing submissions with the Tier 1 risk rules';

    public function handle(ContentRiskService $risk): int
    {
        $query = Submission::query();

        if (! $this->option('all')) {
            $query->whereNull('risk_checked_at');
        }

        if ($status = $this->option('status')) {
            $query->where('status', $status);
        }

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('Nothing to assess.');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $flagged = 0;

        // chunkById, not chunk: the where clause narrows as rows are written,
        // which would make an offset-paged chunk skip half of them.
        $query->chunkById(200, function ($submissions) use ($risk, $bar, &$flagged) {
            foreach ($submissions as $submission) {
                $assessment = $risk->assess((string) $submission->content);

                $submission->forceFill([
                    'risk_score' => $assessment->score,
                    'risk_flags' => $assessment->isClean() ? null : $assessment->flags,
                    'risk_checked_at' => now(),
                ])->save();

                $flagged += $assessment->isClean() ? 0 : 1;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Assessed {$total} submissions, flagged {$flagged}.");

        return self::SUCCESS;
    }
}
