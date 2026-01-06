<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Filesystem;

use Rarus\Echo\Exception\FileException;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Helper for file operations using Symfony Filesystem
 * Provides cross-platform file operations with proper error handling
 */
class FileHelper
{
    private readonly Filesystem $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    /**
     * Check if file exists
     */
    public function exists(string $path): bool
    {
        return $this->filesystem->exists($path);
    }

    /**
     * Check if file is readable
     */
    public function isReadable(string $path): bool
    {
        return $this->exists($path) && is_readable($path);
    }

    /**
     * Get file size in bytes
     *
     * @throws FileException
     */
    public function getFileSize(string $path): int
    {
        if (!$this->exists($path)) {
            throw new FileException("File not found: {$path}");
        }

        $size = @filesize($path);
        if ($size === false) {
            throw new FileException("Unable to get file size: {$path}");
        }

        return $size;
    }

    /**
     * Get file size in megabytes
     *
     * @throws FileException
     */
    public function getFileSizeInMb(string $path): float
    {
        return round($this->getFileSize($path) / 1024 / 1024, 2);
    }

    /**
     * Get MIME type of file
     *
     * @throws FileException
     */
    public function getMimeType(string $path): string
    {
        if (!$this->exists($path)) {
            throw new FileException("File not found: {$path}");
        }

        $mimeType = @mime_content_type($path);
        if ($mimeType === false) {
            return 'application/octet-stream';
        }

        return $mimeType;
    }

    /**
     * Create temporary copy of file
     *
     * @throws FileException
     */
    public function createTempCopy(string $sourcePath): string
    {
        if (!$this->exists($sourcePath)) {
            throw new FileException("Source file not found: {$sourcePath}");
        }

        try {
            $tempPath = $this->filesystem->tempnam(sys_get_temp_dir(), 'rarus_echo_');
            $this->filesystem->copy($sourcePath, $tempPath, true);

            return $tempPath;
        } catch (IOException $e) {
            throw new FileException("Failed to create temp copy: {$e->getMessage()}", $e);
        }
    }

    /**
     * Remove file
     *
     * @throws FileException
     */
    public function remove(string $path): void
    {
        if (!$this->exists($path)) {
            return;
        }

        try {
            $this->filesystem->remove($path);
        } catch (IOException $e) {
            throw new FileException("Failed to remove file: {$e->getMessage()}", $e);
        }
    }

    /**
     * Remove temporary file (suppresses errors)
     */
    public function removeTempFile(string $path): void
    {
        try {
            $this->remove($path);
        } catch (FileException) {
            // Ignore errors when removing temp files
        }
    }

    /**
     * Ensure directory exists, create if needed
     *
     * @throws FileException
     */
    public function ensureDirectoryExists(string $dir, int $mode = 0755): void
    {
        if ($this->exists($dir)) {
            return;
        }

        try {
            $this->filesystem->mkdir($dir, $mode);
        } catch (IOException $e) {
            throw new FileException("Failed to create directory: {$e->getMessage()}", $e);
        }
    }

    /**
     * Get file extension
     */
    public function getExtension(string $path): string
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /**
     * Get filename without extension
     */
    public function getFilenameWithoutExtension(string $path): string
    {
        return pathinfo($path, PATHINFO_FILENAME);
    }

    /**
     * Get basename (filename with extension)
     */
    public function getBasename(string $path): string
    {
        return basename($path);
    }

    /**
     * Format bytes to human-readable string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), $precision) . ' ' . $units[$power];
    }

    /**
     * Check if file is empty
     *
     * @throws FileException
     */
    public function isEmpty(string $path): bool
    {
        return $this->getFileSize($path) === 0;
    }
}
