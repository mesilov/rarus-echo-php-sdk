<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Infrastructure\Console\Media\MediaPreparer;
use Rarus\Echo\Infrastructure\Console\Media\PreparationPolicy;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitLocator;
use Rarus\Echo\Infrastructure\Filesystem\FileHelper;

final class MediaPreparerTest extends TestCase
{
    public function testOrdinaryAudioRetainsOriginalPathWithoutTools(): void
    {
        $path = dirname(__DIR__, 4) . '/Assets/ru/examp-1.ogg';
        $prepared = (new MediaPreparer())->prepare([$path], new MediaOptions(), static function (string $message): void {
        });

        try {
            self::assertSame([$path], $prepared->paths());
        } finally {
            $prepared->cleanup();
        }
        self::assertFileExists($path);
    }

    public function testExplicitBypassDoesNotRequireToolkitOrReadFiles(): void
    {
        $prepared = (new MediaPreparer())->prepare(['video.mp4'], new MediaOptions(noExtractAudio: true), static function (string $message): void {
        });
        self::assertSame(['video.mp4'], $prepared->paths());
        $prepared->cleanup();
    }

    public function testManualSelectionRequiresActualVideoAndRejectsAudioBeforeTools(): void
    {
        $this->expectExceptionMessage('--audio-stream');
        (new MediaPreparer())->prepare([dirname(__DIR__, 4) . '/Assets/ru/examp-1.ogg'], new MediaOptions(audioStream: 0), static function (string $message): void {
        });
    }

    public function testBatchFailureRemovesEarlierPreparedFile(): void
    {
        $directory = sys_get_temp_dir() . '/echo-preparation-test-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $script = '#!' . PHP_BINARY . "\n<?php\n" . <<<'CODE'
            if (in_array('-version', $argv, true)) { echo basename($argv[0]).' version 9.0.2'; exit; }
            if (in_array('-protocols', $argv, true)) { echo "Supported file protocols:\nInput:\n file\n pipe\nOutput:\n file\n pipe\n"; exit; }
            foreach (['-decoders', '-encoders', '-demuxers', '-muxers', '-filters'] as $option) {
                if (in_array($option, $argv, true)) { foreach (explode(' ', 'mov matroska avi mp3 ogg wav flac aac asf mpegts mpeg ipod ac3 eac3 opus vorbis alac wmav1 wmav2 pcm_s16le pcm_s24le pcm_s32le pcm_f32le asetpts adelay aresample aformat') as $name) echo " A..... $name fixture\n"; exit; }
            }
            $file = $argv[count($argv)-1];
            if (in_array('-show_frames', $argv, true)) { echo 'best_effort_timestamp_time=0|nb_samples=48000'; exit; }
            if (in_array('-show_streams', $argv, true)) {
                $audio = ['index'=>0,'codec_type'=>'audio','codec_name'=>str_contains($file,'prepared-')?'flac':'aac','sample_rate'=>'48000','channels'=>2,'start_time'=>'0','duration'=>'1','bits_per_raw_sample'=>is_file(dirname($argv[0]).'/force-precision-loss')?16:24];
                $streams = str_contains($file,'no-audio') ? [] : [$audio];
                if (!str_contains($file,'prepared-')) $streams[]=['index'=>1,'codec_type'=>'video'];
                echo json_encode(['format'=>['start_time'=>'0','duration'=>'1','bits_per_raw_sample'=>is_file(dirname($argv[0]).'/force-precision-loss')?16:24],'streams'=>$streams]); exit;
            }
            copy(__FIXTURE__, $file);
            file_put_contents(__MARKER__, $file);
            CODE;
        $script = str_replace(['__FIXTURE__', '__MARKER__'], [var_export(dirname(__DIR__, 4) . '/Fixtures/Media/stereo.wav', true), var_export($directory . '/marker', true)], $script);
        file_put_contents($directory . '/ffmpeg', $script);
        file_put_contents($directory . '/ffprobe', $script);
        chmod($directory . '/ffmpeg', 0o700);
        chmod($directory . '/ffprobe', 0o700);
        $locator = new ToolkitLocator(environment: ['RARUS_ECHO_FFMPEG_PATH' => $directory . '/ffmpeg', 'RARUS_ECHO_FFPROBE_PATH' => $directory . '/ffprobe']);
        $helper = new class () extends FileHelper {
            public bool $locked = false;

            public function remove(string $path): void
            {
                if ($this->locked) {
                    throw new FileException('Fixture upload handle is locked.');
                }
                parent::remove($path);
            }
        };
        $previousLog = ini_set('error_log', $directory . '/cleanup.log');

        try {
            foreach ([[false, false, 'no audio'], [true, false, 'no audio'], [false, true, 'precision']] as [$locked, $precisionLoss, $expected]) {
                if ($precisionLoss) {
                    file_put_contents($directory . '/force-precision-loss', 'fixture');
                }
                $helper->locked = $locked;
                $preparer = new MediaPreparer(locator: $locator, policy: new PreparationPolicy(true, []), files: $helper);

                try {
                    $preparer->prepare([dirname(__DIR__, 4) . '/Fixtures/Media/video-aac.mp4', dirname(__DIR__, 4) . '/Fixtures/Media/no-audio.mp4'], new MediaOptions(), static function (string $message): void {
                    });
                    self::fail('Expected no-audio failure.');
                } catch (\RuntimeException $exception) {
                    self::assertStringContainsString($expected, $exception->getMessage());
                    self::assertFileExists($directory . '/marker');
                    $preparedPath = (string) file_get_contents($directory . '/marker');
                    if ($locked) {
                        self::assertFileExists($preparedPath);
                        self::assertStringContainsString('Cannot remove prepared media', (string) file_get_contents($directory . '/cleanup.log'));
                        $helper->locked = false;
                        $helper->remove($preparedPath);
                        rmdir(dirname($preparedPath));
                    } else {
                        self::assertFileDoesNotExist($preparedPath);
                    }
                }
            }
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
            foreach (glob($directory . '/*') ?: [] as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testAllUnambiguousSupportedAudioMimeTypesAvoidToolkitDependency(): void
    {
        foreach (['audio/m4a', 'audio/x-m4a', 'audio/webm'] as $mime) {
            $helper = new class ($mime) extends FileHelper {
                public function __construct(private readonly string $mime)
                {
                    parent::__construct();
                }

                public function getMimeType(string $path): string
                {
                    return $this->mime;
                }
            };
            $path = dirname(__DIR__, 4) . '/Fixtures/Media/audio-only.m4a';
            $set = (new MediaPreparer(locator: new ToolkitLocator(environment: []), files: $helper))->prepare([$path], new MediaOptions(), static function (string $message): void {
            });
            self::assertSame([$path], $set->paths());
            $set->cleanup();
        }
    }
}
