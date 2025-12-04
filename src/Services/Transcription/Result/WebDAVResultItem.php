<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

/**
 * Single WebDAV result item
 */
final class WebDAVResultItem
{
    public function __construct(
        private readonly string $filePath,
        private readonly string $status,
        private readonly ?string $fileId = null,
        private readonly ?string $error = null
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            filePath: $data['file_path'] ?? '',
            status: $data['status'] ?? 'failure',
            fileId: $data['file_id'] ?? null,
            error: $data['error'] ?? null
        );
    }

    public function getFilePath(): string
    {
        return $this->filePath;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getFileId(): ?string
    {
        return $this->fileId;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Check if file was uploaded successfully
     */
    public function isSuccess(): bool
    {
        return $this->status === 'success';
    }

    /**
     * Check if file upload failed
     */
    public function isFailure(): bool
    {
        return $this->status === 'failure';
    }

    /**
     * Check if file has warning
     */
    public function isWarning(): bool
    {
        return $this->status === 'warning';
    }
}
