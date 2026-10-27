<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Diagnostics;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Diagnostics\FfmpegCheck;
use Rarus\Echo\Infrastructure\Console\Media\Toolkit;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitLocator;

final class FfmpegCheckCorpusTest extends TestCase
{
    public function testBundledToolkitIsReadyWithAnEmptyPath(): void
    {
        if (getenv('RARUS_ECHO_RUN_MEDIA_ACCEPTANCE') !== '1') {
            self::markTestSkipped('Requires the explicit final bundled media tool pair.');
        }
        $path = getenv('PATH');
        putenv('PATH=');

        try {
            $result = (new FfmpegCheck())->run();
            self::assertSame('ok', $result->status, $result->message);
            self::assertSame('ffmpeg_ok', $result->code);
            self::assertSame(['path' => getenv('RARUS_ECHO_FFMPEG_PATH'), 'version' => '9.0.2'], $result->details);
            $ffmpeg = getenv('RARUS_ECHO_FFMPEG_PATH');
            self::assertIsString($ffmpeg);
            $bundle = dirname($ffmpeg, 2);
            $locator = new ToolkitLocator(executable: $bundle . '/rarus-echo', environment: [], identityPath: $bundle . '/absent-cli-build-info.json');
            $portable = (new FfmpegCheck(static fn (float $deadline): Toolkit => $locator->locate($deadline)))->run();
            self::assertSame('ffmpeg_ok', $portable->code, $portable->message);
            self::assertSame($result->details, $portable->details);
        } finally {
            putenv($path === false ? 'PATH' : 'PATH=' . $path);
        }
    }
}
