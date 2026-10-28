<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\AudioExtractor;
use Rarus\Echo\Infrastructure\Console\Media\MediaCancellationException;
use Rarus\Echo\Infrastructure\Console\Media\MediaOptions;
use Rarus\Echo\Tests\Unit\Infrastructure\Console\Support\FakeMediaPreparer;
use Symfony\Component\Filesystem\Filesystem;

final class AudioExtractorTest extends TestCase
{
    private string $directory;
    private FakeMediaPreparer $preparer;
    private string $source;
    private string $prepared;

    #[\Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/echo-extract-' . bin2hex(random_bytes(8));
        mkdir($this->directory, 0o700);
        $this->directory = (string) realpath($this->directory);
        $this->source = $this->directory . '/встреча $; name.mp4';
        file_put_contents($this->source, 'original video');
        $this->preparer = new FakeMediaPreparer();
        $this->prepared = $this->preparer->set->allocate('m4a');
        file_put_contents($this->prepared, 'complete validated audio');
        $this->preparer->set->add($this->source, $this->prepared);
    }

    #[\Override]
    protected function tearDown(): void
    {
        $this->preparer->set->cleanup();
        (new Filesystem())->remove($this->directory);
    }

    public function testSavesSiblingWithExactBasenameAndCleansPreparedAudio(): void
    {
        $options = new MediaOptions(audioStream: 2, timeout: 42);
        $output = (new AudioExtractor($this->preparer))->extract($this->source, $options, static function (): void {
        });
        self::assertSame($this->target(), $output);
        self::assertSame('complete validated audio', file_get_contents($output));
        self::assertSame('original video', file_get_contents($this->source));
        self::assertSame($options, $this->preparer->options);
        self::assertFileDoesNotExist($this->prepared);
        self::assertSame([], glob($this->directory . '/.rarus-echo-audio-*'));
    }

    public function testNeverOverwritesExistingFileDirectoryOrDanglingSymlink(): void
    {
        foreach (['file', 'directory', 'link'] as $kind) {
            $target = $this->target();
            if ($kind === 'file') {
                file_put_contents($target, 'keep me');
            } elseif ($kind === 'directory') {
                mkdir($target);
            } else {
                if (PHP_OS_FAMILY === 'Windows') {
                    continue;
                }
                symlink($this->directory . '/missing', $target);
            }

            try {
                (new AudioExtractor($this->preparer))->extract($this->source, new MediaOptions(), static function (): void {
                });
                self::fail('An existing destination was replaced.');
            } catch (\RuntimeException $exception) {
                self::assertStringContainsString('exists', $exception->getMessage());
                if ($kind === 'file') {
                    self::assertSame('keep me', file_get_contents($target));
                } elseif ($kind === 'directory') {
                    self::assertDirectoryExists($target);
                } else {
                    self::assertTrue(is_link($target));
                }
            } finally {
                (new Filesystem())->remove($target);
            }
        }
    }

    public function testConcurrentDestinationIsNotReplaced(): void
    {
        $target = $this->target();
        $files = new class ($target) extends Filesystem {
            public function __construct(private readonly string $target)
            {
            }

            public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void
            {
                parent::copy($originFile, $targetFile, $overwriteNewerFiles);
                file_put_contents($this->target, 'concurrent writer');
            }
        };

        try {
            (new AudioExtractor($this->preparer, $files))->extract($this->source, new MediaOptions(), static function (): void {
            });
            self::fail('A concurrent destination was replaced.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('publish', $exception->getMessage());
        }
        self::assertSame('concurrent writer', file_get_contents($target));
        self::assertFileDoesNotExist($this->prepared);
        self::assertSame([], glob($this->directory . '/.rarus-echo-audio-*'));
    }

    public function testCopyFailureAndCancellationRemovePartialStaging(): void
    {
        $files = new class () extends Filesystem {
            public function copy(string $originFile, string $targetFile, bool $overwriteNewerFiles = false): void
            {
                file_put_contents($targetFile, 'partial');

                throw new MediaCancellationException(130);
            }
        };

        try {
            (new AudioExtractor($this->preparer, $files))->extract($this->source, new MediaOptions(), static function (): void {
            });
            self::fail('Copy cancellation was ignored.');
        } catch (MediaCancellationException $exception) {
            self::assertSame(130, $exception->exitCode);
        }
        self::assertFileDoesNotExist($this->target());
        self::assertFileDoesNotExist($this->prepared);
        self::assertSame([], glob($this->directory . '/.rarus-echo-audio-*'));
    }

    public function testAudioOnlyInputIsRejectedWithoutCopyingOrDeletingSource(): void
    {
        $this->preparer->set->cleanup();
        $this->preparer = new FakeMediaPreparer();
        $this->preparer->set->add($this->source, $this->source);
        $this->expectExceptionMessage('video');

        try {
            (new AudioExtractor($this->preparer))->extract($this->source, new MediaOptions(), static function (): void {
            });
        } finally {
            self::assertSame('original video', file_get_contents($this->source));
            self::assertFileDoesNotExist($this->target());
        }
    }

    public function testCancellationImmediatelyAfterCreatingStagingCleansDirectory(): void
    {
        $files = new class () extends Filesystem {
            /** @param string|iterable<string> $dirs */
            public function mkdir(string|iterable $dirs, int $mode = 0o777): void
            {
                parent::mkdir($dirs, $mode);

                throw new MediaCancellationException(130);
            }
        };

        try {
            (new AudioExtractor($this->preparer, $files))->extract($this->source, new MediaOptions(), static function (): void {
            });
            self::fail('Staging cancellation was ignored.');
        } catch (MediaCancellationException $exception) {
            self::assertSame(130, $exception->exitCode);
        }
        self::assertFileDoesNotExist($this->target());
        self::assertFileDoesNotExist($this->prepared);
        self::assertSame([], glob($this->directory . '/.rarus-echo-audio-*'));
    }

    public function testInputSymlinkUsesSuppliedNameAndDirectory(): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            self::markTestSkipped('Symlink creation requires Windows privileges.');
        }
        mkdir($this->directory . '/alias');
        $alias = $this->directory . '/alias/другое имя.mp4';
        symlink($this->source, $alias);
        $output = (new AudioExtractor($this->preparer))->extract($alias, new MediaOptions(), static function (): void {
        });
        self::assertSame($this->directory . '/alias/другое имя.m4a', $output);
        self::assertTrue(is_link($alias));
    }

    private function target(): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . 'встреча $; name.m4a';
    }
}
