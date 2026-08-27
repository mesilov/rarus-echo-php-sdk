<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Services\Transcription\Request;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Services\Transcription\Request\TranscriptionOptions;

final class TranscriptionOptionsTest extends TestCase
{
    public function testDefaultHeadersDisableExtendedTimestamps(): void
    {
        $headers = TranscriptionOptions::default()->toHeaders();

        $this->assertSame('0', $headers['timestamps-extended']);
    }

    public function testBuilderEnablesExtendedTimestampsHeader(): void
    {
        $headers = TranscriptionOptions::create()
            ->withTimestampsExtended()
            ->build()
            ->toHeaders();

        $this->assertSame('1', $headers['timestamps-extended']);
    }

    public function testBuilderCanDisableExtendedTimestampsAfterEnablingIt(): void
    {
        $headers = TranscriptionOptions::create()
            ->withTimestampsExtended()
            ->withTimestampsExtended(false)
            ->build()
            ->toHeaders();

        $this->assertSame('0', $headers['timestamps-extended']);
    }
}
