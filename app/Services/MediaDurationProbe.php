<?php

namespace App\Services;

/**
 * Reads how long a video runs by parsing the container directly.
 *
 * Shelling out to ffprobe is not an option: production is shared hosting with
 * no ffmpeg binary, and its composer.lock sits ahead of the repo's, so pulling
 * in a metadata package would mean a `composer install` that downgrades the
 * live app. Both containers we accept keep the duration in a small header near
 * the start of the file, so reading it costs a handful of seeks.
 *
 * MP4/MOV store it in the `mvhd` box inside `moov`; WebM/Matroska store it in
 * the EBML `Duration` element under `Segment > Info`. Anything we cannot parse
 * returns null, which callers treat as "reject" rather than "allow".
 */
class MediaDurationProbe
{
    /** Box types an ISO base media file may legitimately open with. */
    private const ISO_SIGNATURES = ['ftyp', 'moov', 'mdat', 'free', 'skip', 'wide', 'pnot'];

    private const EBML_SIGNATURE = "\x1A\x45\xDF\xA3";

    private const EBML_ID_SEGMENT = 0x18538067;

    private const EBML_ID_INFO = 0x1549A966;

    private const EBML_ID_TIMECODE_SCALE = 0x2AD7B1;

    private const EBML_ID_DURATION = 0x4489;

    /** Matroska's default tick length, in nanoseconds, when Info omits one. */
    private const DEFAULT_TIMECODE_SCALE = 1000000;

    public function durationInSeconds(string $path): ?float
    {
        if (! is_file($path) || ! is_readable($path)) {
            return null;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $header = (string) fread($handle, 8);

            if (str_starts_with($header, self::EBML_SIGNATURE)) {
                return $this->matroskaDuration($handle);
            }

            if (in_array(substr($header, 4, 4), self::ISO_SIGNATURES, true)) {
                return $this->isoDuration($handle);
            }

            return null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * MP4/MOV: find `moov` at the top level, then its `mvhd` child, and divide
     * the duration by the timescale it is expressed in.
     *
     * @param  resource  $handle
     */
    private function isoDuration($handle): ?float
    {
        $moov = $this->findIsoBox($handle, 'moov', 0, $this->sizeOf($handle));

        if ($moov === null) {
            return null;
        }

        $mvhd = $this->findIsoBox($handle, 'mvhd', $moov[0], $moov[1]);

        if ($mvhd === null) {
            return null;
        }

        fseek($handle, $mvhd[0]);
        $version = ord((string) fread($handle, 1));
        fread($handle, 3); // flags

        if ($version === 1) {
            fread($handle, 16); // creation + modification time
            $timescale = $this->toUnsignedInt((string) fread($handle, 4));
            $duration = $this->toUnsignedInt((string) fread($handle, 8));
            // An all-ones 64-bit duration means "unknown"; it also happens to be
            // the only way this can come back negative once PHP wraps it.
            $unknown = $duration !== null && $duration < 0;
        } else {
            fread($handle, 8); // creation + modification time
            $timescale = $this->toUnsignedInt((string) fread($handle, 4));
            $duration = $this->toUnsignedInt((string) fread($handle, 4));
            $unknown = $duration === 0xFFFFFFFF;
        }

        if ($timescale === null || $timescale <= 0 || $duration === null || $duration <= 0 || $unknown) {
            return null;
        }

        return $duration / $timescale;
    }

    /**
     * Walk the sibling boxes between $start and $end looking for $type, and
     * return the [start, end] offsets of its payload.
     *
     * @param  resource  $handle
     * @return array{0: int, 1: int}|null
     */
    private function findIsoBox($handle, string $type, int $start, int $end): ?array
    {
        $offset = $start;

        while ($offset + 8 <= $end) {
            fseek($handle, $offset);
            $header = (string) fread($handle, 8);

            if (strlen($header) < 8) {
                return null;
            }

            $size = $this->toUnsignedInt(substr($header, 0, 4));
            $boxType = substr($header, 4, 4);
            $headerSize = 8;

            if ($size === 1) {
                // 32 bits were not enough for this box; a 64-bit size follows.
                $size = $this->toUnsignedInt((string) fread($handle, 8));
                $headerSize = 16;
            } elseif ($size === 0) {
                // A zero size means the box runs to the end of the file.
                $size = $end - $offset;
            }

            if ($size === null || $size < $headerSize) {
                return null;
            }

            if ($boxType === $type) {
                return [$offset + $headerSize, min($end, $offset + $size)];
            }

            $offset += $size;
        }

        return null;
    }

    /**
     * WebM/Matroska: Duration is a float counted in TimecodeScale-sized ticks.
     *
     * @param  resource  $handle
     */
    private function matroskaDuration($handle): ?float
    {
        $fileSize = $this->sizeOf($handle);

        $segment = $this->findEbmlElement($handle, self::EBML_ID_SEGMENT, 0, $fileSize);

        if ($segment === null) {
            return null;
        }

        $info = $this->findEbmlElement($handle, self::EBML_ID_INFO, $segment[0], $segment[1]);

        if ($info === null) {
            return null;
        }

        $duration = $this->findEbmlElement($handle, self::EBML_ID_DURATION, $info[0], $info[1]);

        if ($duration === null) {
            return null;
        }

        $ticks = $this->readEbmlFloat($handle, $duration[0], $duration[1] - $duration[0]);

        if ($ticks === null || $ticks < 0) {
            return null;
        }

        $timecodeScale = self::DEFAULT_TIMECODE_SCALE;
        $scale = $this->findEbmlElement($handle, self::EBML_ID_TIMECODE_SCALE, $info[0], $info[1]);

        if ($scale !== null) {
            fseek($handle, $scale[0]);
            $value = $this->toUnsignedInt((string) fread($handle, $scale[1] - $scale[0]));

            if ($value !== null && $value > 0) {
                $timecodeScale = $value;
            }
        }

        return $ticks * $timecodeScale / 1000000000;
    }

    /**
     * Scan the EBML elements between $start and $end for $targetId, returning
     * the [start, end] offsets of its payload.
     *
     * @param  resource  $handle
     * @return array{0: int, 1: int}|null
     */
    private function findEbmlElement($handle, int $targetId, int $start, int $end): ?array
    {
        $offset = $start;

        while ($offset < $end) {
            fseek($handle, $offset);

            $id = $this->readEbmlVint($handle, stripMarker: false);
            $size = $id === null ? null : $this->readEbmlVint($handle, stripMarker: true);

            if ($id === null || $size === null) {
                return null;
            }

            $payloadStart = $offset + $id['length'] + $size['length'];

            // Live-muxed files write an unknown size for Segment; treat those
            // as running to the end of the range we were handed.
            $payloadEnd = $size['unknown'] ? $end : min($end, $payloadStart + $size['value']);

            if ($id['value'] === $targetId) {
                return [$payloadStart, $payloadEnd];
            }

            if ($payloadEnd <= $offset) {
                return null;
            }

            $offset = $payloadEnd;
        }

        return null;
    }

    /**
     * EBML variable-length integer. The leading zero bits of the first byte say
     * how many bytes the number occupies; element IDs keep those marker bits
     * while sizes strip them.
     *
     * @param  resource  $handle
     * @return array{value: int, length: int, unknown: bool}|null
     */
    private function readEbmlVint($handle, bool $stripMarker): ?array
    {
        $first = (string) fread($handle, 1);

        if ($first === '') {
            return null;
        }

        $byte = ord($first);

        if ($byte === 0) {
            // Would mean a vint longer than 8 bytes; no real file uses one.
            return null;
        }

        $length = 1;

        for ($mask = 0x80; ($byte & $mask) === 0; $mask >>= 1) {
            $length++;
        }

        $dataMask = 0xFF >> $length;
        $value = $stripMarker ? $byte & $dataMask : $byte;
        $unknown = ($byte & $dataMask) === $dataMask;

        for ($i = 1; $i < $length; $i++) {
            $next = (string) fread($handle, 1);

            if ($next === '') {
                return null;
            }

            $byte = ord($next);
            $unknown = $unknown && $byte === 0xFF;
            $value = ($value << 8) | $byte;
        }

        return ['value' => $value, 'length' => $length, 'unknown' => $stripMarker && $unknown];
    }

    /** @param  resource  $handle */
    private function readEbmlFloat($handle, int $start, int $length): ?float
    {
        if ($length !== 4 && $length !== 8) {
            return null;
        }

        fseek($handle, $start);
        $bytes = (string) fread($handle, $length);

        if (strlen($bytes) !== $length) {
            return null;
        }

        // G and E are the big-endian float and double formats EBML uses.
        $unpacked = unpack($length === 4 ? 'G' : 'E', $bytes);

        return $unpacked === false ? null : (float) $unpacked[1];
    }

    private function toUnsignedInt(string $bytes): ?int
    {
        $length = strlen($bytes);

        if ($length === 0 || $length > 8) {
            return null;
        }

        $value = 0;

        for ($i = 0; $i < $length; $i++) {
            $value = ($value << 8) | ord($bytes[$i]);
        }

        return $value;
    }

    /** @param  resource  $handle */
    private function sizeOf($handle): int
    {
        $stat = fstat($handle);

        return is_array($stat) ? (int) $stat['size'] : 0;
    }
}
