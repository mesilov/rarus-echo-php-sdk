<?php

declare(strict_types=1);

namespace Rarus\Echo\Infrastructure\Console\Media;

/** @internal Resolves only explicit or identity-bound offline tools. */
final readonly class ToolkitLocator
{
    /** @param null|array<string, string> $environment */
    public function __construct(
        private MediaProcessRunner $runner = new MediaProcessRunner(),
        private ?string $executable = null,
        private ?array $environment = null,
        private ?string $identityPath = null
    ) {
    }

    public function locate(float $deadline): Toolkit
    {
        $environment = $this->environment ?? getenv();
        $ffmpeg = $environment['RARUS_ECHO_FFMPEG_PATH'] ?? null;
        $ffprobe = $environment['RARUS_ECHO_FFPROBE_PATH'] ?? null;
        if ($ffmpeg !== null || $ffprobe !== null) {
            if (!is_string($ffmpeg) || !is_string($ffprobe) || !$this->absolute($ffmpeg) || !$this->absolute($ffprobe)) {
                throw new \RuntimeException('RARUS_ECHO_FFMPEG_PATH and RARUS_ECHO_FFPROBE_PATH must be supplied together as absolute paths.');
            }
            $tools = new Toolkit($ffmpeg, $ffprobe);
            $this->capabilities($tools, $deadline);

            return $tools;
        }
        $identityPath = $this->identityPath ?? dirname(__DIR__, 4) . '/build-info.json';
        $identity = is_file($identityPath) ? $this->json($identityPath) : null;
        $executable = $this->executable ?? (defined('RARUS_ECHO_EXECUTABLE') ? constant('RARUS_ECHO_EXECUTABLE') : null);
        $directories = [];
        if (is_string($executable) && $this->absolute($executable)) {
            $directory = dirname($executable);
            $directories[] = $directory;
            if (is_array($identity) && is_string($identity['version'] ?? null) && preg_match('/^[a-zA-Z0-9._+\-]+$/D', $identity['version']) === 1
                && is_string($identity['source_sha'] ?? null) && preg_match('/^[0-9a-f]{40}$/D', $identity['source_sha']) === 1) {
                $directories[] = $directory . '/.rarus-echo/packages/' . $identity['version'] . '-' . $identity['source_sha'];
            }
        }
        $directories[] = '/opt/rarus-echo/media';
        foreach ($directories as $directory) {
            if (!is_file($directory . '/media-build-info.json')) {
                continue;
            }
            $tools = $this->bundle($directory, $identity);
            $this->capabilities($tools, $deadline);

            return $tools;
        }

        throw new \RuntimeException('Media toolkit unavailable. Keep the complete portable/installed package, or set both absolute RARUS_ECHO_FFMPEG_PATH and RARUS_ECHO_FFPROBE_PATH values for Composer.');
    }

    /** @param null|array<string, mixed> $identity */
    private function bundle(string $directory, ?array $identity): Toolkit
    {
        $manifest = $this->json($directory . '/media-build-info.json');
        if (($manifest['schema_version'] ?? null) !== 1 || !is_string($manifest['ffmpeg_version'] ?? null)
            || !is_string($manifest['source_sha256'] ?? null) || !is_string($manifest['profile_sha256'] ?? null)
            || !is_array($manifest['binaries'] ?? null) || !is_array($manifest['source_package'] ?? null)) {
            throw new \RuntimeException('Invalid media manifest.');
        }
        if ($identity !== null) {
            foreach (['ffmpeg_version', 'source_sha256', 'profile_sha256'] as $key) {
                if (($identity['media'][$key] ?? null) !== $manifest[$key]) {
                    throw new \RuntimeException('Media manifest does not match this CLI build identity.');
                }
            }
        }
        $suffix = PHP_OS_FAMILY === 'Windows' ? '.exe' : '';
        $paths = [];
        foreach (['ffmpeg', 'ffprobe'] as $name) {
            $expected = 'libexec/' . $name . $suffix;
            $record = $manifest['binaries'][$name] ?? null;
            if (!is_array($record) || ($record['path'] ?? null) !== $expected) {
                throw new \RuntimeException('Invalid media manifest binary path.');
            }
            $path = $directory . '/' . $expected;
            $this->verifyHash($path, $record['sha256'] ?? null);
            $paths[$name] = $path;
        }
        if (($manifest['source_package']['path'] ?? null) !== 'media-source.tar.xz') {
            throw new \RuntimeException('Invalid media manifest corresponding-source path.');
        }
        $this->verifyHash($directory . '/media-source.tar.xz', $manifest['source_package']['sha256'] ?? null);

        return new Toolkit($paths['ffmpeg'], $paths['ffprobe']);
    }

    private function verifyHash(string $path, mixed $expected): void
    {
        if (!is_string($expected) || preg_match('/^[0-9a-f]{64}$/D', $expected) !== 1 || !is_file($path) || is_link($path) || hash_file('sha256', $path) !== $expected) {
            throw new \RuntimeException('Media bundle hash verification failed: ' . basename($path));
        }
    }

    private function capabilities(Toolkit $tools, float $deadline): void
    {
        foreach (['ffmpeg' => $tools->ffmpeg, 'ffprobe' => $tools->ffprobe] as $program => $binary) {
            if (!is_file($binary) || (PHP_OS_FAMILY !== 'Windows' && !is_executable($binary))) {
                throw new \RuntimeException('Media tool is not executable: ' . $binary);
            }
            $version = $this->runner->run([$binary, '-version'], $deadline);
            if (preg_match('/^' . $program . ' version (?:n)?9\.0\.2(?:[\s-]|$)/', $version) !== 1 || str_contains($version, '--enable-gpl') || str_contains($version, '--enable-nonfree')) {
                throw new \RuntimeException('Media tool version/profile is incompatible; expected LGPL FFmpeg 9.0.2.');
            }
            $protocols = $this->runner->run([$binary, '-hide_banner', '-protocols'], $deadline);
            preg_match_all('/^\s*([a-z0-9_]+)\s*$/m', $protocols, $matches);
            if ($matches[1] === [] || array_diff($matches[1], ['file', 'pipe']) !== []) {
                throw new \RuntimeException('Media profile enables unsupported network or other protocols.');
            }
            $decoders = $this->runner->run([$binary, '-hide_banner', '-decoders'], $deadline);
            if (preg_match('/^\s*[VS][A-Z.]{5}\s+[a-zA-Z0-9_]+\s+/m', $decoders) === 1) {
                throw new \RuntimeException('Media profile enables unsupported video or subtitle decoders.');
            }

        }
        $required = [
            '-demuxers' => ['mov', 'matroska', 'avi', 'mp3', 'ogg', 'wav', 'flac', 'aac', 'asf', 'mpegts', 'mpeg'],
            '-muxers' => ['ipod', 'ogg', 'mp3', 'flac'],
            '-decoders' => ['aac', 'ac3', 'eac3', 'mp3', 'opus', 'vorbis', 'flac', 'alac', 'wmav1', 'wmav2', 'pcm_s16le', 'pcm_s24le', 'pcm_s32le', 'pcm_f32le'],
            '-encoders' => ['flac'],
            '-filters' => ['asetpts', 'adelay', 'aresample', 'aformat'],
        ];
        foreach ($required as $option => $components) {
            $capabilities = $this->runner->run([$tools->ffmpeg, '-hide_banner', $option], $deadline);
            preg_match_all('/^\s*[A-Z.]{1,8}\s+([a-zA-Z0-9_,]+)\s+\S/m', $capabilities, $rows);
            $available = explode(',', implode(',', $rows[1]));
            if ($option === '-encoders' && preg_match('/^\s*[VS][A-Z.]{5}\s+[a-zA-Z0-9_]+\s+/m', $capabilities) === 1) {
                throw new \RuntimeException('Media profile enables unsupported video or subtitle encoders.');
            }
            foreach ($components as $component) {
                if (!in_array($component, $available, true)) {
                    throw new \RuntimeException('Media profile lacks required capability: ' . $component);
                }
            }
        }
    }

    /** @return array<string, mixed> */
    private function json(string $path): array
    {
        $size = filesize($path);
        if ($size === false || $size > 1048576) {
            throw new \RuntimeException('Invalid media manifest size.');
        }
        $value = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \RuntimeException('Invalid media manifest JSON.');
        }

        return $value;
    }

    private function absolute(string $path): bool
    {
        return str_starts_with($path, '/') || preg_match('/^[a-zA-Z]:[\\\\\/]/', $path) === 1 || str_starts_with($path, '\\\\');
    }
}
