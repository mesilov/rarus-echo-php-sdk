<?php

declare(strict_types=1);

namespace Rarus\Echo\Tests\Unit\Infrastructure\Console\Media;

use PHPUnit\Framework\TestCase;
use Rarus\Echo\Infrastructure\Console\Media\ToolkitLocator;

final class ToolkitLocatorTest extends TestCase
{
    public function testOverrideRequiresAbsolutePairBeforeStartingAnyProcess(): void
    {
        $this->expectExceptionMessage('absolute paths');
        (new ToolkitLocator(environment: ['RARUS_ECHO_FFMPEG_PATH' => 'ffmpeg']))->locate(microtime(true) + 1);
    }

    public function testInvalidAdjacentManifestFailsWithoutFallback(): void
    {
        $directory = sys_get_temp_dir() . '/echo-locator-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        file_put_contents($directory . '/media-build-info.json', '{}');

        try {
            $this->expectExceptionMessage('media manifest');
            (new ToolkitLocator(executable: $directory . '/rarus-echo', environment: []))->locate(microtime(true) + 1);
        } finally {
            unlink($directory . '/media-build-info.json');
            rmdir($directory);
        }
    }

    public function testOverrideRejectsNetworkEnabledToolkit(): void
    {
        $directory = sys_get_temp_dir() . '/echo-profile-' . bin2hex(random_bytes(8));
        mkdir($directory, 0o700);
        $script = '#!' . PHP_BINARY . "\n<?php\n" . <<<'CODE'
            if (in_array('-version', $argv, true)) { echo basename($argv[0]).' version 9.0.2'; exit; }
            if (in_array('-protocols', $argv, true)) { echo "Supported file protocols:\nInput:\n  file\n  pipe\n  tcp\nOutput:\n file\n pipe\n"; exit; }
            foreach (explode(" ", "mov matroska avi mp3 ogg wav flac aac asf mpegts mpeg ipod ac3 eac3 opus vorbis alac wmav1 wmav2 pcm_s16le pcm_s24le pcm_s32le pcm_f32le asetpts adelay aresample aformat") as $name) echo " A..... $name fixture\n";
            CODE;
        foreach (['ffmpeg', 'ffprobe'] as $name) {
            file_put_contents($directory . '/' . $name, $script);
            chmod($directory . '/' . $name, 0o700);
        }

        try {
            $this->expectExceptionMessage('network');
            (new ToolkitLocator(environment: ['RARUS_ECHO_FFMPEG_PATH' => $directory . '/ffmpeg', 'RARUS_ECHO_FFPROBE_PATH' => $directory . '/ffprobe']))->locate(microtime(true) + 5);
        } finally {
            unlink($directory . '/ffmpeg');
            unlink($directory . '/ffprobe');
            rmdir($directory);
        }
    }
}
