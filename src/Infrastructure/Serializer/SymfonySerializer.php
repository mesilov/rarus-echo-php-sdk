<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Serializer;

use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\BackedEnumNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;

/**
 * Symfony Serializer implementation
 */
final class SymfonySerializer implements SerializerInterface
{
    private readonly Serializer $serializer;

    public function __construct()
    {
        // Property info extractors for type detection
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();

        $propertyTypeExtractor = new PropertyInfoExtractor(
            typeExtractors: [$reflectionExtractor, $phpDocExtractor]
        );

        // Normalizers
        $normalizers = [
            new DateTimeNormalizer(),
            new BackedEnumNormalizer(),
            new ArrayDenormalizer(),
            new ObjectNormalizer(
                propertyTypeExtractor: $propertyTypeExtractor
            ),
        ];

        // Encoders
        $encoders = [new JsonEncoder()];

        $this->serializer = new Serializer($normalizers, $encoders);
    }

    public function serialize(mixed $data): string
    {
        return $this->serializer->serialize($data, 'json');
    }

    public function deserialize(string $json, string $type): mixed
    {
        return $this->serializer->deserialize($json, $type, 'json');
    }

    public function denormalize(array $data, string $type): mixed
    {
        return $this->serializer->denormalize($data, $type);
    }

    public function normalize(mixed $data): array
    {
        $normalized = $this->serializer->normalize($data);

        if (!is_array($normalized)) {
            throw new \RuntimeException('Normalization must return an array');
        }

        return $normalized;
    }
}
