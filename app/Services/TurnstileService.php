<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class TurnstileService
{
    // Returns true if the token is valid. If no secret key is configured
    // (local dev), verification is skipped so the flow isn't blocked.
    public function verify(?string $token, string $remoteIp): bool
    {
        $secret = config('services.turnstile.secret');

        if (! $secret) {
            return true;
        }

        if (! $token) {
            return false;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $remoteIp,
        ]);

        return $response->json('success') === true;
    }
}
