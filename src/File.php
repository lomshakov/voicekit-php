<?php

declare(strict_types=1);

namespace VoiceKit;

use InvalidArgumentException;

/**
 * A file sent with a multipart/form-data request (transcription, analysis,
 * voice cloning, effects, …).
 *
 * ```php
 * $client->transcribe(File::fromPath('call.mp3'), language: 'ru');
 * $client->transcribe(File::fromString($bytes, 'clip.wav'));
 * $client->transcribe('call.mp3'); // a plain path works too
 * ```
 */
final class File
{
    public const DEFAULT_MIME = 'application/octet-stream';

    private string $contents;

    private string $name;

    private string $contentType;

    public function __construct(string $contents, string $name = 'audio.wav', ?string $contentType = null)
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'audio.wav';
        }

        $this->contents = $contents;
        $this->name = $name;
        $this->contentType = $contentType ?? self::mimeTypeFor($name);
    }

    /**
     * Builds a file from an in-memory payload.
     */
    public static function fromString(string $contents, string $name = 'audio.wav', ?string $contentType = null): self
    {
        return new self($contents, $name, $contentType);
    }

    /**
     * Reads a file from disk; the MIME type is guessed from the extension.
     *
     * @throws InvalidArgumentException when the file is missing or unreadable.
     */
    public static function fromPath(string $path, ?string $name = null, ?string $contentType = null): self
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new InvalidArgumentException(sprintf('VoiceKit: file "%s" does not exist or is not readable.', $path));
        }

        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new InvalidArgumentException(sprintf('VoiceKit: unable to read file "%s".', $path));
        }

        $basename = basename($path);

        return new self(
            $contents,
            $name ?? $basename,
            $contentType ?? self::mimeTypeFor($name ?? $basename),
        );
    }

    /**
     * Normalises the many shapes a caller may pass (a path, a {@see File}, …)
     * into a single {@see File}.
     *
     * @throws InvalidArgumentException
     */
    public static function of(self|string $value, string $defaultName = 'audio.wav'): self
    {
        return $value instanceof self ? $value : self::fromPath($value, $defaultName);
    }

    /**
     * Normalises a single file or a list of files.
     *
     * @param self|string|array<int, self|string> $value
     *
     * @return list<self>
     *
     * @throws InvalidArgumentException
     */
    public static function collect(self|string|array $value, string $defaultName = 'audio.wav'): array
    {
        if ($value instanceof self || is_string($value)) {
            return [self::of($value, $defaultName)];
        }

        if ($value === []) {
            throw new InvalidArgumentException('VoiceKit: at least one file is required.');
        }

        $files = [];
        foreach ($value as $item) {
            if (!($item instanceof self) && !is_string($item)) {
                throw new InvalidArgumentException('VoiceKit: expected a File or a file path.');
            }

            $files[] = self::of($item, $defaultName);
        }

        return $files;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function contentType(): string
    {
        return $this->contentType;
    }

    public function contents(): string
    {
        return $this->contents;
    }

    public function size(): int
    {
        return strlen($this->contents);
    }

    /**
     * The payload encoded as base64, ready for inline batch items.
     *
     * @see \VoiceKit\VoiceKitClient::batchAnalyze()
     */
    public function base64(): string
    {
        return base64_encode($this->contents);
    }

    /**
     * Reads a file and encodes it as base64 for inline batch items.
     *
     * @throws InvalidArgumentException
     */
    public static function base64Of(self|string $value): string
    {
        return self::of($value)->base64();
    }

    /**
     * A copy with a different file name.
     */
    public function withName(string $name): self
    {
        return new self($this->contents, $name, $this->contentType);
    }

    /**
     * A copy with a different MIME type.
     */
    public function withContentType(string $contentType): self
    {
        return new self($this->contents, $this->name, $contentType);
    }

    /**
     * Guesses a MIME type from a file name or path.
     */
    public static function mimeTypeFor(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg', 'opus' => 'audio/ogg',
            'm4a', 'aac' => 'audio/aac',
            'flac' => 'audio/flac',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => self::DEFAULT_MIME,
        };
    }
}
