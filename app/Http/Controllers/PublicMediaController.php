<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PublicMediaController extends Controller
{
    public function show(string $path): BinaryFileResponse
    {
        $disk = Storage::disk('public');

        abort_if(str_contains($path, '..') || ! $disk->exists($path), 404);

        // Streamed from disk rather than read into a string: a video is orders
        // of magnitude larger than a photo, and the byte-range support that
        // comes with a file response is what lets <video> seek at all — Safari
        // will not play a source that cannot answer a Range request.
        return response()->file($disk->path($path), [
            'Cache-Control' => 'public, max-age=31536000',
            'Content-Type' => $disk->mimeType($path) ?: 'application/octet-stream',
        ]);
    }
}
