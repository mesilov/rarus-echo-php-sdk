<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Configuration\ConfigFileProtection;
use Rarus\Echo\Infrastructure\Console\Media\PreparedFileSet;

final class PreparedFileSetTest extends TestCase
{
    public function testPerFileOverflowRemovesOwnedSparseOutputOnly(): void
    {
        $set = new PreparedFileSet();
        $path = $set->allocate('flac');
        $handle = fopen($path, 'wb');
        self::assertIsResource($handle);
        ftruncate($handle, 500 * 1024 * 1024 + 1);
        fclose($handle);

        try {
            $set->checkBudget();
            self::fail('Expected upload cap failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('500 MiB', $exception->getMessage());
        } finally {
            $set->cleanup();
        }
        self::assertFileDoesNotExist($path);
    }

    public function testAggregateBudgetIncludesReserveForBoundedProbeOutput(): void
    {
        $set = new PreparedFileSet();
        $paths = [];
        for ($index = 0; $index < 2; ++$index) {
            $path = $set->allocate('flac');
            $paths[] = $path;
            $handle = fopen($path, 'wb');
            self::assertIsResource($handle);
            ftruncate($handle, 500 * 1024 * 1024);
            fclose($handle);
        }

        try {
            $set->checkBudget();
            self::fail('Expected aggregate cap failure.');
        } catch (\RuntimeException $exception) {
            self::assertStringContainsString('1 GiB', $exception->getMessage());
        } finally {
            $set->cleanup();
        }
        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
    }

    public function testPrivateDirectoryProtectionFailureLeavesNoFilesOrDirectory(): void
    {
        $protection = new class () extends ConfigFileProtection {
            public ?string $path = null;

            public function protect(string $path): void
            {
                $this->path = $path;

                throw new \RuntimeException('ACL fixture refused');
            }
        };

        try {
            (new PreparedFileSet($protection))->allocate('flac');
            self::fail('Expected protection failure.');
        } catch (\RuntimeException $exception) {
            self::assertSame('ACL fixture refused', $exception->getMessage());
            self::assertNotNull($protection->path);
            self::assertDirectoryDoesNotExist($protection->path);
        }
    }
}
