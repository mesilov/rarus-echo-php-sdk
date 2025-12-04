<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Filesystem;

use Rarus\Echo\Exception\FileException;
use Rarus\Echo\Exception\ValidationException;

/**
 * File uploader for preparing files for multipart/form-data upload
 */
final class FileUploader
{
    public function __construct(
        private readonly FileHelper $fileHelper,
        private readonly FileValidator $validator
    ) {
    }

    /**
     * Prepare files for multipart upload
     *
     * @param array<string> $filePaths
     *
     * @return array<int, array{name: string, contents: resource, filename: string, headers: array<string, string>}>
     *
     * @throws ValidationException
     * @throws FileException
     */
    public function prepareFiles(array $filePaths): array
    {
        // Validate all files first
        $this->validator->validateMultiple($filePaths);

        $preparedFiles = [];

        foreach ($filePaths as $filePath) {
            $preparedFiles[] = $this->prepareFile($filePath);
        }

        return $preparedFiles;
    }

    /**
     * Prepare single file for upload
     *
     * @return array{name: string, contents: resource, filename: string, headers: array<string, string>}
     *
     * @throws FileException
     */
    public function prepareFile(string $filePath): array
    {
        $resource = @fopen($filePath, 'r');
        if ($resource === false) {
            throw new FileException("Unable to open file for reading: {$filePath}");
        }

        return [
            'name' => 'files',
            'contents' => $resource,
            'filename' => $this->fileHelper->getBasename($filePath),
            'headers' => [
                'Content-Type' => $this->fileHelper->getMimeType($filePath),
            ],
        ];
    }

    /**
     * Cleanup file resources after upload
     *
     * @param array<int, array{contents: resource}> $resources
     */
    public function cleanup(array $resources): void
    {
        foreach ($resources as $resource) {
            if (isset($resource['contents']) && is_resource($resource['contents'])) {
                @fclose($resource['contents']);
            }
        }
    }

    /**
     * Create file stream from path
     *
     * @return resource
     *
     * @throws FileException
     */
    public function createStream(string $filePath)
    {
        if (!$this->fileHelper->exists($filePath)) {
            throw new FileException("File not found: {$filePath}");
        }

        $stream = @fopen($filePath, 'r');
        if ($stream === false) {
            throw new FileException("Unable to create stream from file: {$filePath}");
        }

        return $stream;
    }

    /**
     * Get file info for logging/debugging
     *
     * @param array<string> $filePaths
     *
     * @return array<int, array{path: string, size: string, mime: string}>
     */
    public function getFilesInfo(array $filePaths): array
    {
        $info = [];

        foreach ($filePaths as $filePath) {
            try {
                $info[] = [
                    'path' => $filePath,
                    'size' => $this->fileHelper->formatBytes($this->fileHelper->getFileSize($filePath)),
                    'mime' => $this->fileHelper->getMimeType($filePath),
                ];
            } catch (\Exception $e) {
                $info[] = [
                    'path' => $filePath,
                    'size' => 'unknown',
                    'mime' => 'unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $info;
    }
}
