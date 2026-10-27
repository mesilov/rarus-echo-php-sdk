<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\DecodedTimeline;

final class DecodedTimelineTest extends TestCase
{
    public function testMeasuresFullDecodedAudioIncludingPrimingInsteadOfContainerDuration(): void
    {
        $timeline = $this->timeline("best_effort_timestamp_time=2.000000|nb_samples=1024\nbest_effort_timestamp_time=2.021333|nb_samples=512\n");
        self::assertSame(2.0, $timeline->start);
        self::assertSame(1536, $timeline->samples);
        self::assertSame(0.032, $timeline->duration);
    }

    public function testRejectsInternalGapsInsteadOfSilentlyRebasingSpeech(): void
    {
        $this->expectExceptionMessage('discontinuous');
        $this->timeline("best_effort_timestamp_time=0|nb_samples=1024\nbest_effort_timestamp_time=1|nb_samples=1024\n");
    }

    public function testTwoHourAudioUsesBoundedStreamingMemory(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'echo-long-timeline-');
        self::assertIsString($path);
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);

        try {
            for ($frame = 0; $frame < 337500; ++$frame) {
                fwrite($handle, sprintf("best_effort_timestamp_time=%.6f|nb_samples=1024\n", $frame * 1024 / 48000));
            }
            fclose($handle);
            self::assertLessThan(64 * 1024 * 1024, filesize($path));
            $before = memory_get_usage(true);
            $timeline = DecodedTimeline::fromCompactFile($path, 48000);
            self::assertSame(7200.0, $timeline->duration);
            self::assertSame(345600000, $timeline->samples);
            self::assertLessThan(4 * 1024 * 1024, memory_get_usage(true) - $before);
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            unlink($path);
        }
    }

    private function timeline(string $compact): DecodedTimeline
    {
        $path = tempnam(sys_get_temp_dir(), 'echo-timeline-');
        self::assertIsString($path);
        file_put_contents($path, $compact);

        try {
            return DecodedTimeline::fromCompactFile($path, 48000);
        } finally {
            unlink($path);
        }
    }
}
