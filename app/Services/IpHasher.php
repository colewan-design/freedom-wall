<?php

namespace App\Services;

class IpHasher
{
    // Hashes the submitter's IP for rate-limiting/abuse tracking — the raw
    // IP itself is never stored.
    public function hash(string $ip): string
    {
        return hash('sha256', $ip.config('services.ip_salt'));
    }
}
