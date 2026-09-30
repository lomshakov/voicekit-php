<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use VoiceKit\File;

final class FileTest extends TestCase
{
    private string $temporaryPath;

    protected function setUp(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'voicekit');
        if ($path === false) {
            $this->fail('Unable to create a temporary file.');
        }

        $this->temporaryPath = $path;
        file_put_contents($this->temporaryPath, 'RIFFdata');
    }

    protected function tearDown(): void
    {
        if (is_file($this->temporaryPath)) {
            unlink($this->temporaryPath);
        }
    }

    public function test_builds_from_a_string(): void
    {
        $file = File::fromString('abc', 'clip.mp3');

        $this->assertSame('clip.mp3', $file->name());
        $this->assertSame('audio/mpeg', $file->contentType());
        $this->assertSame(3, $file->size());
        $this->assertSame(base64_encode('abc'), $file->base64());
    }

    public function test_builds_from_a_path_and_guesses_the_mime_type(): void
    {
        $file = File::fromPath($this->temporaryPath);

        $this->assertSame('RIFFdata', $file->contents());
        $this->assertSame(File::DEFAULT_MIME, $file->contentType());
        $this->assertSame(base64_encode('RIFFdata'), File::base64Of($this->temporaryPath));
    }

    public function test_rejects_a_missing_path(): void
    {
        $this->expectException(InvalidArgumentException::class);

        File::fromPath($this->temporaryPath . '-missing');
    }

    public function test_copies_with_overrides(): void
    {
        $file = File::fromString('abc')->withName('call.wav')->withContentType('audio/x-custom');

        $this->assertSame('call.wav', $file->name());
        $this->assertSame('audio/x-custom', $file->contentType());
        $this->assertSame('abc', $file->contents());
    }

    public function test_normalises_single_values_and_lists(): void
    {
        $single = File::collect(File::fromString('abc', 'call.wav'));
        $this->assertCount(1, $single);
        $this->assertSame('call.wav', $single[0]->name());

        $many = File::collect([File::fromString('a'), File::fromString('b')]);
        $this->assertCount(2, $many);

        $this->expectException(InvalidArgumentException::class);
        File::collect([]);
    }

    public function test_collect_rejects_unsupported_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        /** @phpstan-ignore-next-line intentionally wrong input */
        File::collect([123]);
    }

    public function test_maps_known_extensions(): void
    {
        $this->assertSame('audio/wav', File::mimeTypeFor('a.WAV'));
        $this->assertSame('audio/ogg', File::mimeTypeFor('a.opus'));
        $this->assertSame('audio/aac', File::mimeTypeFor('a.m4a'));
        $this->assertSame('video/mp4', File::mimeTypeFor('a.mp4'));
        $this->assertSame('video/webm', File::mimeTypeFor('a.webm'));
        $this->assertSame('application/octet-stream', File::mimeTypeFor('a.txt'));
    }
}
