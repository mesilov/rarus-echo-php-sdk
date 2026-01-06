<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Filesystem;

use Rarus\Echo\Exception\ValidationException;

/**
 * Validator for media files before transcription
 */
final readonly class FileValidator
{
    /**
     * Allowed MIME types for audio and video files
     */
    private const array ALLOWED_MIME_TYPES = [
        // Audio
        'audio/mpeg',
        'audio/mp3',
        'audio/wav',
        'audio/x-wav',
        'audio/wave',
        'audio/x-pn-wav',
        'audio/ogg',
        'audio/flac',
        'audio/x-flac',
        'audio/aac',
        'audio/m4a',
        'audio/x-m4a',
        'audio/webm',
        // Video
        'video/mp4',
        'video/mpeg',
        'video/x-msvideo',
        'video/avi',
        'video/quicktime',
        'video/x-matroska',
        'video/webm',
    ];

    /**
     * Maximum file size in bytes (500 MB)
     */
    private const MAX_FILE_SIZE = 500 * 1024 * 1024;

    public function __construct(
        private FileHelper $fileHelper
    ) {
    }

    /**
     * Validate single file
     *
     * @throws ValidationException
     */
    public function validate(string $filePath): void
    {
        $this->validateExists($filePath);
        $this->validateReadable($filePath);
        $this->validateSize($filePath);
        $this->validateMimeType($filePath);
    }

    /**
     * Validate multiple files
     *
     * @param array<string> $filePaths
     *
     * @throws ValidationException
     */
    public function validateMultiple(array $filePaths): void
    {
        if ($filePaths === []) {
            throw new ValidationException('No files provided for validation');
        }

        foreach ($filePaths as $filePath) {
            $this->validate($filePath);
        }
    }

    /**
     * Check if file is valid without throwing exception
     */
    public function isValid(string $filePath): bool
    {
        try {
            $this->validate($filePath);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    /**
     * Validate file exists
     *
     * @throws ValidationException
     */
    private function validateExists(string $filePath): void
    {
        if (!$this->fileHelper->exists($filePath)) {
            throw new ValidationException("File does not exist: {$filePath}");
        }
    }

    /**
     * Validate file is readable
     *
     * @throws ValidationException
     */
    private function validateReadable(string $filePath): void
    {
        if (!$this->fileHelper->isReadable($filePath)) {
            throw new ValidationException("File is not readable: {$filePath}");
        }
    }

    /**
     * Validate file size
     *
     * @throws ValidationException
     */
    private function validateSize(string $filePath): void
    {
        try {
            $fileSize = $this->fileHelper->getFileSize($filePath);
        } catch (\Exception $e) {
            throw new ValidationException("Unable to get file size: {$e->getMessage()}");
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new ValidationException(
                sprintf(
                    "File size (%s) exceeds maximum allowed size (%s): %s",
                    $this->fileHelper->formatBytes($fileSize),
                    $this->fileHelper->formatBytes(self::MAX_FILE_SIZE),
                    $filePath
                )
            );
        }

        if ($fileSize === 0) {
            throw new ValidationException("File is empty: {$filePath}");
        }
    }

    /**
     * Validate MIME type
     *
     * @throws ValidationException
     */
    private function validateMimeType(string $filePath): void
    {
        try {
            $mimeType = $this->fileHelper->getMimeType($filePath);
        } catch (\Exception $e) {
            throw new ValidationException("Unable to detect MIME type: {$e->getMessage()}");
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new ValidationException(
                sprintf(
                    "File MIME type '%s' is not supported. File: %s\nAllowed types: %s",
                    $mimeType,
                    $filePath,
                    implode(', ', self::ALLOWED_MIME_TYPES)
                )
            );
        }
    }

    /**
     * Get allowed MIME types
     *
     * @return array<string>
     */
    public static function getAllowedMimeTypes(): array
    {
        return self::ALLOWED_MIME_TYPES;
    }

    /**
     * Get maximum file size in bytes
     */
    public static function getMaxFileSize(): int
    {
        return self::MAX_FILE_SIZE;
    }

    /**
     * Get maximum file size in megabytes
     */
    public static function getMaxFileSizeInMb(): float
    {
        return round(self::MAX_FILE_SIZE / 1024 / 1024, 2);
    }
}
