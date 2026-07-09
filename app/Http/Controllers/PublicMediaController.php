<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicMediaController extends Controller
{
    public function show(string $path): Response
    {
        abort_if(
            str_contains($path, '..') || ! Storage::disk('public')->exists($path),
            404,
        );

        return response(
            Storage::disk('public')->get($path),
            200,
            [
                'Cache-Control' => 'public, max-age=31536000',
                'Content-Type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
            ],
        );
    }
}
