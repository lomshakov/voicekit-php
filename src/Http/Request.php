<?php

declare(strict_types=1);

namespace VoiceKit\Http;

/**
 * An outgoing HTTP request handed to a {@see Transport}.
 */
final class Request
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
    ) {
    }

    /**
     * A copy with a different request body.
     */
    public function withBody(?string $body): self
    {
        return new self($this->method, $this->url, $this->headers, $body);
    }

    /**
     * A copy with one extra header.
     */
    public function withHeader(string $name, string $value): self
    {
        $headers = $this->headers;
        $headers[$name] = $value;

        return new self($this->method, $this->url, $headers, $this->body);
    }
}
