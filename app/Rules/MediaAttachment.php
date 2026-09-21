<?php

namespace App\Rules;

use App\Services\MediaDurationProbe;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * One rule for every file a composer can attach, so the photo limits and the
 * video limits cannot drift apart between the wall and the student feed.
 *
 * Size has to be checked here rather than with `max:` because photos and videos
 * get very different budgets, and `images.*` sees both.
 */
class MediaAttachment implements ValidationRule
{
    public const MAX_ATTACHMENTS = 4;

    /** Videos are large; one per post keeps storage on shared hosting sane. */
    public const MAX_VIDEOS = 1;

    public const MAX_VIDEO_SECONDS = 60;

    public const MAX_IMAGE_KILOBYTES = 5120;

    public const MAX_VIDEO_KILOBYTES = 102400;

    /** Mime type => the extension we store the file under. */
    public const IMAGE_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * `application/mp4` is the registered type for the MP4 container and is
     * what several sniffers return for an ordinary phone recording, so it has
     * to be accepted alongside `video/mp4`. Nothing gets in on the mime type
     * alone — a video still has to yield a readable duration below.
     */
    public const VIDEO_TYPES = [
        'video/mp4' => 'mp4',
        'application/mp4' => 'mp4',
        'video/quicktime' => 'mov',
        'video/webm' => 'webm',
    ];

    /**
     * Phone cameras routinely overshoot a one-minute stop by a few frames;
     * rejecting a 60.1s clip would read as a bug to the person recording it.
     */
    private const DURATION_TOLERANCE_SECONDS = 0.5;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('That file could not be uploaded. Please try again.');

            return;
        }

        $mimeType = (string) $value->getMimeType();

        if (isset(self::IMAGE_TYPES[$mimeType])) {
            $this->validateImage($value, $fail);

            return;
        }

        if (isset(self::VIDEO_TYPES[$mimeType])) {
            $this->validateVideo($value, $fail);

            return;
        }

        $fail('Attachments must be a JPG, PNG, or WebP photo, or an MP4, MOV, or WebM video.');
    }

    public static function isVideo(mixed $file): bool
    {
        return $file instanceof UploadedFile
            && isset(self::VIDEO_TYPES[(string) $file->getMimeType()]);
    }

    /**
     * The extension to store the file under. Derived from the detected mime
     * type rather than the uploaded filename, which the client controls — and
     * which the feed relies on to decide whether to render an img or a video.
     */
    public static function extensionFor(UploadedFile $file): string
    {
        $mimeType = (string) $file->getMimeType();

        return self::IMAGE_TYPES[$mimeType] ?? self::VIDEO_TYPES[$mimeType] ?? 'bin';
    }

    private function validateImage(UploadedFile $file, Closure $fail): void
    {
        if ($file->getSize() > self::MAX_IMAGE_KILOBYTES * 1024) {
            $fail('Photos must be '.self::MAX_IMAGE_KILOBYTES / 1024 .'MB or smaller.');
        }
    }

    private function validateVideo(UploadedFile $file, Closure $fail): void
    {
        if ($file->getSize() > self::MAX_VIDEO_KILOBYTES * 1024) {
            $fail('Videos must be '.self::MAX_VIDEO_KILOBYTES / 1024 .'MB or smaller.');

            return;
        }

        $seconds = app(MediaDurationProbe::class)->durationInSeconds((string) $file->getRealPath());

        if ($seconds === null) {
            $fail('We could not read how long that video is. Please re-save it as an MP4 and try again.');

            return;
        }

        if ($seconds > self::MAX_VIDEO_SECONDS + self::DURATION_TOLERANCE_SECONDS) {
            $fail(sprintf(
                'Videos can be at most %d seconds long. That clip runs %s.',
                self::MAX_VIDEO_SECONDS,
                self::formatDuration($seconds),
            ));
        }
    }

    private static function formatDuration(float $seconds): string
    {
        $whole = (int) round($seconds);

        return $whole < 60
            ? $whole.' seconds'
            : sprintf('%d:%02d', intdiv($whole, 60), $whole % 60);
    }
}
