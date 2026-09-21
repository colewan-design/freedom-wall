<?php

namespace Tests\Support;

/**
 * Builds the smallest MP4 and WebM files that still carry a real duration
 * header, so MediaDurationProbe can be tested against actual container bytes
 * without checking binary fixtures into the repo.
 */
class FakeVideo
{
    /** An `ftyp` + `moov > mvhd` MP4 whose movie header reports $seconds. */
    public static function mp4(float $seconds, bool $sixtyFourBit = false, int $timescale = 600): string
    {
        $duration = (int) round($seconds * $timescale);

        $mvhd = $sixtyFourBit
            ? "\x01\x00\x00\x00"                        // version 1, no flags
                .pack('J', 0).pack('J', 0)              // creation + modification time
                .pack('N', $timescale)
                .pack('J', $duration)
            : "\x00\x00\x00\x00"                        // version 0, no flags
                .pack('N', 0).pack('N', 0)              // creation + modification time
                .pack('N', $timescale)
                .pack('N', $duration);

        // rate, volume, reserved, matrix, predefined, next track id
        $mvhd .= str_repeat("\x00", 80);

        return self::box('ftyp', 'isom'.pack('N', 512).'isomiso2mp41')
            .self::box('moov', self::box('mvhd', $mvhd));
    }

    /** An EBML `Segment > Info` WebM whose Duration reports $seconds. */
    public static function webm(float $seconds, int $timecodeScale = 1000000): string
    {
        $ticks = $seconds * 1000000000 / $timecodeScale;

        $info = self::element("\x2A\xD7\xB1", substr(pack('N', $timecodeScale), 1))
            .self::element("\x44\x89", pack('E', $ticks));

        return self::element("\x1A\x45\xDF\xA3", self::element("\x42\x82", 'webm'))
            .self::element("\x18\x53\x80\x67", self::element("\x15\x49\xA9\x66", $info));
    }

    private static function box(string $type, string $payload): string
    {
        return pack('N', 8 + strlen($payload)).$type.$payload;
    }

    private static function element(string $id, string $payload): string
    {
        return $id.self::vintSize(strlen($payload)).$payload;
    }

    private static function vintSize(int $size): string
    {
        return $size < 0x7F
            ? chr(0x80 | $size)
            : pack('N', 0x10000000 | $size);
    }
}
