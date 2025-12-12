<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Serializer;

/**
 * Interface for serialization/deserialization
 */
interface SerializerInterface
{
    /**
     * Serialize data to JSON string
     *
     * @param mixed $data
     */
    public function serialize(mixed $data): string;

    /**
     * Deserialize JSON string to object
     *
     * @template T
     *
     * @param class-string<T> $type
     *
     * @return T
     */
    public function deserialize(string $json, string $type): mixed;

    /**
     * Denormalize array to object
     *
     * @template T
     *
     * @param array<string, mixed> $data
     * @param class-string<T>      $type
     *
     * @return T
     */
    public function denormalize(array $data, string $type): mixed;

    /**
     * Normalize object to array
     *
     * @param mixed $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data): array;
}
