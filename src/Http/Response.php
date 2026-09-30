<?php

declare(strict_types=1);

namespace VoiceKit\Http;

/**
 * An HTTP response returned by a {@see Transport}.
 */
final class Response
{
    /**
     * @param array<string, string> $headers header names lower-cased.
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status <= 299;
    }

    /**
     * A response header, case-insensitively, or `null` when absent.
     */
    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function contentType(): ?string
    {
        return $this->header('content-type');
    }
}
