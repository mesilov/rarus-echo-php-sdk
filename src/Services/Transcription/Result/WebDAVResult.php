<?php

declare(strict_types=1);

namespace Rarus\Echo\Services\Transcription\Result;

/**
 * WebDAV (Rarus Drive) upload result
 */
final class WebDAVResult
{
    /**
     * @param array<int, WebDAVResultItem> $result
     */
    public function __construct(
        private readonly array $result
    ) {
    }

    /**
     * Create from API response
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $items = array_map(
            fn (array $item) => WebDAVResultItem::fromArray($item),
            $data['result'] ?? []
        );

        return new self($items);
    }

    /**
     * Get all result items
     *
     * @return array<int, WebDAVResultItem>
     */
    public function getResult(): array
    {
        return $this->result;
    }

    /**
     * Get successful items
     *
     * @return array<int, WebDAVResultItem>
     */
    public function getSuccessful(): array
    {
        return array_filter(
            $this->result,
            fn (WebDAVResultItem $item) => $item->isSuccess()
        );
    }

    /**
     * Get failed items
     *
     * @return array<int, WebDAVResultItem>
     */
    public function getFailed(): array
    {
        return array_filter(
            $this->result,
            fn (WebDAVResultItem $item) => $item->isFailure()
        );
    }

    /**
     * Get count of all items
     */
    public function getCount(): int
    {
        return count($this->result);
    }

    /**
     * Get count of successful items
     */
    public function getSuccessCount(): int
    {
        return count($this->getSuccessful());
    }

    /**
     * Get count of failed items
     */
    public function getFailureCount(): int
    {
        return count($this->getFailed());
    }
}
