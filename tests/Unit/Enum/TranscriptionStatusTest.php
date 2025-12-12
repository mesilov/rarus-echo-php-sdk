<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Enum;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Enum\TranscriptionStatus;

final class TranscriptionStatusTest extends TestCase
{
    public function testIsFinal(): void
    {
        $this->assertTrue(TranscriptionStatus::SUCCESS->isFinal());
        $this->assertTrue(TranscriptionStatus::FAILURE->isFinal());
        $this->assertFalse(TranscriptionStatus::WAITING->isFinal());
        $this->assertFalse(TranscriptionStatus::PROCESSING->isFinal());
    }

    public function testIsInProgress(): void
    {
        $this->assertTrue(TranscriptionStatus::WAITING->isInProgress());
        $this->assertTrue(TranscriptionStatus::PROCESSING->isInProgress());
        $this->assertFalse(TranscriptionStatus::SUCCESS->isInProgress());
        $this->assertFalse(TranscriptionStatus::FAILURE->isInProgress());
    }

    public function testGetDescription(): void
    {
        $this->assertSame('Waiting in queue', TranscriptionStatus::WAITING->getDescription());
        $this->assertSame('Processing', TranscriptionStatus::PROCESSING->getDescription());
        $this->assertSame('Completed successfully', TranscriptionStatus::SUCCESS->getDescription());
        $this->assertSame('Failed with error', TranscriptionStatus::FAILURE->getDescription());
    }
}
