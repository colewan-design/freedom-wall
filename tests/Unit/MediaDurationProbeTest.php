<?php

namespace Tests\Unit;

use App\Services\MediaDurationProbe;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeVideo;

class MediaDurationProbeTest extends TestCase
{
    private MediaDurationProbe $probe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->probe = new MediaDurationProbe;
    }

    public function test_it_reads_the_duration_of_an_mp4(): void
    {
        $this->assertEqualsWithDelta(42.5, $this->probeBytes(FakeVideo::mp4(42.5)), 0.01);
    }

    public function test_it_reads_a_sixty_four_bit_movie_header(): void
    {
        $this->assertEqualsWithDelta(
            125.0,
            $this->probeBytes(FakeVideo::mp4(125.0, sixtyFourBit: true)),
            0.01,
        );
    }

    public function test_it_reads_the_duration_of_a_webm(): void
    {
        $this->assertEqualsWithDelta(17.25, $this->probeBytes(FakeVideo::webm(17.25)), 0.01);
    }

    public function test_it_honours_a_non_default_timecode_scale(): void
    {
        $this->assertEqualsWithDelta(
            9.0,
            $this->probeBytes(FakeVideo::webm(9.0, timecodeScale: 100000)),
            0.01,
        );
    }

    public function test_it_returns_null_for_something_that_is_not_a_video(): void
    {
        $this->assertNull($this->probeBytes('this is just a text file'));
    }

    public function test_it_returns_null_when_the_movie_header_is_missing(): void
    {
        // An MP4 container with no moov box: valid enough to sniff, useless to read.
        $this->assertNull($this->probeBytes(pack('N', 16).'ftyp'.'isom'.pack('N', 512)));
    }

    public function test_it_returns_null_for_a_missing_file(): void
    {
        $this->assertNull($this->probe->durationInSeconds(__DIR__.'/does-not-exist.mp4'));
    }

    private function probeBytes(string $bytes): ?float
    {
        $path = tempnam(sys_get_temp_dir(), 'probe');
        file_put_contents($path, $bytes);

        try {
            return $this->probe->durationInSeconds($path);
        } finally {
            unlink($path);
        }
    }
}
