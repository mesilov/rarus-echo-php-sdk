<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Filesystem;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Infrastructure\Filesystem\FileHelper;

final class FileHelperTest extends TestCase
{
    private FileHelper $fileHelper;
    private string $tempDir;

    #[\Override]
    protected function setUp(): void
    {
        $this->fileHelper = new FileHelper();
        $this->tempDir = sys_get_temp_dir() . '/rarus_echo_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);
    }

    #[\Override]
    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testExists(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'test content');

        $this->assertTrue($this->fileHelper->exists($filePath));
        $this->assertFalse($this->fileHelper->exists($this->tempDir . '/nonexistent.txt'));
    }

    public function testIsReadable(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'test content');

        $this->assertTrue($this->fileHelper->isReadable($filePath));
        $this->assertFalse($this->fileHelper->isReadable($this->tempDir . '/nonexistent.txt'));
    }

    public function testGetFileSize(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        $content = 'test content';
        file_put_contents($filePath, $content);

        $size = $this->fileHelper->getFileSize($filePath);

        $this->assertSame(strlen($content), $size);
    }

    public function testGetFileSizeThrowsExceptionForNonexistentFile(): void
    {
        $this->expectException(FileException::class);
        $this->expectExceptionMessage('File not found');

        $this->fileHelper->getFileSize($this->tempDir . '/nonexistent.txt');
    }

    public function testGetFileSizeInMb(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, str_repeat('a', 1024 * 1024)); // 1 MB

        $sizeMb = $this->fileHelper->getFileSizeInMb($filePath);

        $this->assertEqualsWithDelta(1.0, $sizeMb, 0.01);
    }

    public function testGetMimeType(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'test content');

        $mimeType = $this->fileHelper->getMimeType($filePath);

        $this->assertStringContainsString('text/', $mimeType);
    }

    public function testGetExtension(): void
    {
        $this->assertSame('txt', $this->fileHelper->getExtension('/path/to/file.txt'));
        $this->assertSame('mp3', $this->fileHelper->getExtension('/path/to/audio.mp3'));
        $this->assertSame('mp3', $this->fileHelper->getExtension('/path/to/audio.MP3'));
        $this->assertSame('', $this->fileHelper->getExtension('/path/to/file'));
    }

    public function testGetBasename(): void
    {
        $this->assertSame('file.txt', $this->fileHelper->getBasename('/path/to/file.txt'));
        $this->assertSame('audio.mp3', $this->fileHelper->getBasename('/path/to/audio.mp3'));
    }

    public function testGetFilenameWithoutExtension(): void
    {
        $this->assertSame('file', $this->fileHelper->getFilenameWithoutExtension('/path/to/file.txt'));
        $this->assertSame('audio', $this->fileHelper->getFilenameWithoutExtension('/path/to/audio.mp3'));
    }

    public function testFormatBytes(): void
    {
        $this->assertSame('0 B', $this->fileHelper->formatBytes(0));
        $this->assertSame('1.00 KB', $this->fileHelper->formatBytes(1024));
        $this->assertSame('1.00 MB', $this->fileHelper->formatBytes(1024 * 1024));
        $this->assertSame('1.00 GB', $this->fileHelper->formatBytes(1024 * 1024 * 1024));
    }

    public function testEnsureDirectoryExists(): void
    {
        $newDir = $this->tempDir . '/subdir';

        $this->assertFalse($this->fileHelper->exists($newDir));

        $this->fileHelper->ensureDirectoryExists($newDir);

        $this->assertTrue($this->fileHelper->exists($newDir));
        $this->assertTrue(is_dir($newDir));
    }

    public function testRemove(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'test content');

        $this->assertTrue($this->fileHelper->exists($filePath));

        $this->fileHelper->remove($filePath);

        $this->assertFalse($this->fileHelper->exists($filePath));
    }

    public function testIsEmpty(): void
    {
        $emptyFile = $this->tempDir . '/empty.txt';
        $nonEmptyFile = $this->tempDir . '/nonempty.txt';

        file_put_contents($emptyFile, '');
        file_put_contents($nonEmptyFile, 'content');

        $this->assertTrue($this->fileHelper->isEmpty($emptyFile));
        $this->assertFalse($this->fileHelper->isEmpty($nonEmptyFile));
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
