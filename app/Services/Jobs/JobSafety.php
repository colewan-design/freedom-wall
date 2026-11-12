<?php

namespace App\Services\Jobs;

use App\Models\JobListing;
use Illuminate\Support\Str;

/**
 * Warning signs worth a second look. These are labels for the reader and the
 * moderator — a listing is never called a scam on the strength of a pattern,
 * and nothing here blocks one from going up.
 */
final class JobSafety
{
    public const LABELS = [
        'upfront_fee' => 'Asks for a fee to apply',
        'suspicious_link' => 'Unusual application link',
        'missing_employer' => 'Employer not named',
        'unrealistic_pay' => 'Pay claim looks too good',
        'reported' => 'Reported by readers',
    ];

    private const SHORTENERS = ['bit.ly', 'tinyurl.com', 'cutt.ly', 'shorturl.at', 'is.gd', 't.ly', 'rb.gy', 'ow.ly', 'tiny.cc'];

    /** @return array<string, string> kind => the evidence that tripped it */
    public function assess(JobListing|NormalizedJob $job): array
    {
        $title = $job->title;
        $employer = $job instanceof JobListing ? $job->employer : $job->employer;
        $url = $job instanceof JobListing ? $job->apply_url : $job->url;
        $description = (string) $job->description;
        $salary = $job instanceof JobListing ? $job->salary_text : $job->salaryText;
        $text = Str::lower($title.' '.$description.' '.$salary);

        $flags = [];

        if (preg_match('/(placement|processing|reservation|registration|training|medical|application|uniform)\s+fee|pay\s+(a\s+)?(fee|deposit)\s+(to|before)|upfront\s+(payment|fee)|bayad\s+(muna|sa)\b|send\s+(gcash|payment)/', $text, $m)) {
            $flags['upfront_fee'] = $m[0];
        }

        $host = Str::lower((string) parse_url((string) $url, PHP_URL_HOST));

        if ($host === '' || in_array($host, self::SHORTENERS, true) || filter_var($host, FILTER_VALIDATE_IP) || Str::lower((string) parse_url((string) $url, PHP_URL_SCHEME)) === 'http') {
            $flags['suspicious_link'] = $host ?: (string) $url;
        }

        if (blank($employer)) {
            $flags['missing_employer'] = 'No employer given';
        }

        // Daily or weekly figures that only make sense as a lure.
        if (preg_match('/(earn|kita|income|salary)[^.\n]{0,30}?(\d[\d,]{3,})\s*(\/|per|a|kada)\s*(day|araw|week)/', $text, $m)
            && (int) str_replace(',', '', $m[2]) >= 5000) {
            $flags['unrealistic_pay'] = $m[0];
        } elseif (preg_match('/guaranteed\s+(income|earnings)|no experience[^.\n]{0,40}(100k|\d{3},\d{3})/', $text, $m)) {
            $flags['unrealistic_pay'] = $m[0];
        }

        return $flags;
    }
}
