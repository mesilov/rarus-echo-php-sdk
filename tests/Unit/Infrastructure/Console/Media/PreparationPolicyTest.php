<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Infrastructure\Console\Media\MediaProbe;
use Rarus\Echo\Infrastructure\Console\Media\PreparationPolicy;

final class PreparationPolicyTest extends TestCase
{
    public function testSelectsOnlyDefaultThenLowestAbsoluteIndexAndIgnoresArtwork(): void
    {
        $probe = MediaProbe::fromJson(json_encode([
            'format' => ['start_time' => '0', 'duration' => '12'],
            'streams' => [
                ['index' => 0, 'codec_type' => 'video', 'disposition' => ['attached_pic' => 1]],
                $this->audio(7, true), $this->audio(2, false),
            ],
        ], JSON_THROW_ON_ERROR));
        self::assertFalse($probe->hasVideo());
        self::assertSame(7, $probe->selectAudio(null)['index']);
        $probe = MediaProbe::fromJson(json_encode(['format' => ['start_time' => '0', 'duration' => '12'], 'streams' => [$this->audio(7, true), $this->audio(2, true)]], JSON_THROW_ON_ERROR));
        self::assertSame(2, $probe->selectAudio(null)['index']);
    }

    public function testPositiveOffsetUsesActualSampleSilenceAndPreservesChannelsAndRate(): void
    {
        $policy = new PreparationPolicy(true, ['aac']);
        $plan = $policy->plan(['codec_name' => 'aac', 'start_time' => '2.5', 'duration' => '12', 'channels' => 2, 'sample_rate' => '48000'], 0.0);
        self::assertSame('flac', $plan->extension);
        self::assertSame(['-c:a', 'flac', '-sample_fmt', 's32', '-bits_per_raw_sample', '24', '-af', 'asetpts=PTS-STARTPTS,adelay=120000S:all=1', '-ar', '48000', '-ac', '2'], $plan->arguments);
        self::assertEqualsWithDelta(14.5, $plan->duration, 0.00001);
    }

    public function testCopyRequiresExplicitEvidenceAndZeroOffset(): void
    {
        $stream = $this->audio(1, false);
        self::assertSame('flac', (new PreparationPolicy(true, []))->plan($stream, 0)->extension);
        self::assertSame('m4a', (new PreparationPolicy(true, ['aac']))->plan($stream, 0)->extension);
    }

    public function testUnverifiedFallbackFailsClosed(): void
    {
        $this->expectExceptionMessage('Echo compatibility');
        (new PreparationPolicy(false, []))->plan($this->audio(1, false), 0);
    }

    public function testMissingOrNegativeTimelineFailsClosed(): void
    {
        $this->expectExceptionMessage('timeline');
        (new PreparationPolicy(true, []))->plan(['codec_name' => 'aac', 'channels' => 2, 'sample_rate' => '48000', 'start_time' => '-1', 'duration' => '12'], 0);
    }

    public function testMediaOptionsRejectAmbiguousManualBatchAndIntegerOverflow(): void
    {
        $this->expectExceptionMessage('--audio-stream');
        MediaOptions::parse('999999999999999999999999', false, '600', 1);
    }

    public function testDecimalOptionValuesAllowLeadingZeroesWithoutOctalInterpretation(): void
    {
        $options = MediaOptions::parse('000', false, '00600', 1);
        self::assertSame(0, $options->audioStream);
        self::assertSame(600, $options->timeout);
    }

    public function testLosslessIntegerPrecisionIsExplicitlyPreserved(): void
    {
        foreach ([['pcm_s16le', 16, 's16', 16], ['pcm_s24le', 24, 's32', 24], ['alac', 20, 's32p', 24]] as [$codec, $bits, $format, $outputBits]) {
            $stream = array_merge($this->audio(1, false), ['codec_name' => $codec, 'bits_per_raw_sample' => $bits, 'sample_fmt' => $format]);
            $plan = (new PreparationPolicy(true, []))->plan($stream, 0);
            self::assertSame($outputBits, $plan->bitsPerSample);
            self::assertSame((string) $outputBits, $plan->arguments[array_search('-bits_per_raw_sample', $plan->arguments, true) + 1]);
        }
    }

    public function testUnrepresentableLosslessSourcePrecisionIsRejected(): void
    {
        foreach ([['codec_name' => 'pcm_s32le', 'bits_per_raw_sample' => 32, 'sample_fmt' => 's32'], ['codec_name' => 'pcm_f32le', 'sample_fmt' => 'flt'], ['codec_name' => 'alac', 'sample_fmt' => 's32p']] as $precision) {
            try {
                (new PreparationPolicy(true, []))->plan(array_merge($this->audio(1, false), $precision), 0);
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('precision', $exception->getMessage());

                continue;
            }
            self::fail('Unrepresentable source was accepted.');
        }
    }

    /** @return array<string, mixed> */
    private function audio(int $index, bool $default): array
    {
        return ['index' => $index, 'codec_type' => 'audio', 'codec_name' => 'aac', 'channels' => 2, 'sample_rate' => '48000', 'start_time' => '0', 'duration' => '12', 'disposition' => ['default' => (int) $default]];
    }
}
