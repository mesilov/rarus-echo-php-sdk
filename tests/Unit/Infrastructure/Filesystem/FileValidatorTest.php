<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Filesystem;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Exception\ValidationException;
use Rarus\Echo\Infrastructure\Filesystem\FileHelper;
use Rarus\Echo\Infrastructure\Filesystem\FileValidator;

final class FileValidatorTest extends TestCase
{
    private FileValidator $validator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->validator = new FileValidator(new FileHelper());
        $this->tempDir = sys_get_temp_dir() . '/rarus_echo_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testValidateNonexistentFile(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File does not exist');

        $this->validator->validate($this->tempDir . '/nonexistent.txt');
    }

    public function testValidateEmptyFile(): void
    {
        $filePath = $this->tempDir . '/empty.txt';
        file_put_contents($filePath, '');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('File is empty');

        $this->validator->validate($filePath);
    }

    public function testValidateTooLargeFile(): void
    {
        $filePath = $this->tempDir . '/large.txt';
        // Create a fake large file info
        file_put_contents($filePath, 'test');

        // Mock FileHelper to return large size
        $fileHelper = $this->createMock(FileHelper::class);
        $fileHelper->method('exists')->willReturn(true);
        $fileHelper->method('isReadable')->willReturn(true);
        $fileHelper->method('getFileSize')->willReturn(501 * 1024 * 1024); // 501 MB
        $fileHelper->method('formatBytes')->willReturnCallback(function ($bytes) {
            return number_format($bytes / 1024 / 1024, 2) . ' MB';
        });
        $fileHelper->method('getMimeType')->willReturn('audio/mpeg');

        $validator = new FileValidator($fileHelper);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('exceeds maximum allowed size');

        $validator->validate($filePath);
    }

    public function testValidateInvalidMimeType(): void
    {
        $filePath = $this->tempDir . '/test.txt';
        file_put_contents($filePath, 'test content');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('MIME type');
        $this->expectExceptionMessage('is not supported');

        $this->validator->validate($filePath);
    }

    public function testValidateMultipleFiles(): void
    {
        $file1 = $this->tempDir . '/file1.txt';
        $file2 = $this->tempDir . '/nonexistent.txt';

        file_put_contents($file1, 'test');

        $this->expectException(ValidationException::class);

        $this->validator->validateMultiple([$file1, $file2]);
    }

    public function testValidateMultipleFilesWithEmptyArray(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('No files provided');

        $this->validator->validateMultiple([]);
    }

    public function testIsValid(): void
    {
        $filePath = $this->tempDir . '/invalid.txt';
        file_put_contents($filePath, 'test');

        // Text file is not a valid media file
        $this->assertFalse($this->validator->isValid($filePath));

        // Non-existent file
        $this->assertFalse($this->validator->isValid($this->tempDir . '/nonexistent.mp3'));
    }

    public function testGetAllowedMimeTypes(): void
    {
        $mimeTypes = FileValidator::getAllowedMimeTypes();

        $this->assertIsArray($mimeTypes);
        $this->assertNotEmpty($mimeTypes);
        $this->assertContains('audio/mpeg', $mimeTypes);
        $this->assertContains('video/mp4', $mimeTypes);
    }

    public function testGetMaxFileSize(): void
    {
        $maxSize = FileValidator::getMaxFileSize();

        $this->assertSame(500 * 1024 * 1024, $maxSize);
    }

    public function testGetMaxFileSizeInMb(): void
    {
        $maxSizeMb = FileValidator::getMaxFileSizeInMb();

        $this->assertSame(500.0, $maxSizeMb);
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
