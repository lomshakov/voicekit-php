<?php

declare(strict_types=1);

namespace VoiceKit\Tests\Support;

use RuntimeException;
use VoiceKit\Http\Request;
use VoiceKit\Http\Response;
use VoiceKit\Http\Transport;

/**
 * A transport double: it records every request and replays queued responses.
 */
final class FakeTransport implements Transport
{
    /** @var list<Request> */
    public array $requests = [];

    /** @var list<Response> */
    private array $responses;

    /**
     * @param list<Response> $responses
     */
    public function __construct(array $responses = [])
    {
        $this->responses = $responses;
    }

    /**
     * @param array<mixed>|string $payload
     */
    public static function ok(array|string $payload = '{}'): Response
    {
        return new Response(200, self::encode($payload), ['content-type' => 'application/json']);
    }

    /**
     * @param array<mixed>|string $payload
     */
    public static function problem(int $status, array|string $payload): Response
    {
        return new Response($status, self::encode($payload), ['content-type' => 'application/problem+json']);
    }

    public function push(Response $response): self
    {
        $this->responses[] = $response;

        return $this;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        return array_shift($this->responses) ?? new Response(200, '{}', ['content-type' => 'application/json']);
    }

    public function lastRequest(): Request
    {
        if ($this->requests === []) {
            throw new RuntimeException('No request has been sent.');
        }

        return $this->requests[count($this->requests) - 1];
    }

    public function lastPath(): string
    {
        return (string) parse_url($this->lastRequest()->url, PHP_URL_PATH);
    }

    /**
     * @return array<string, mixed>
     */
    public function lastJsonBody(): array
    {
        $body = $this->lastRequest()->body ?? '';
        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, string>
     */
    public function lastQuery(): array
    {
        $query = parse_url($this->lastRequest()->url, PHP_URL_QUERY);
        if (!is_string($query)) {
            return [];
        }

        $parsed = [];
        parse_str($query, $parsed);

        $strings = [];
        foreach ($parsed as $key => $value) {
            if (is_string($value)) {
                $strings[(string) $key] = $value;
            }
        }

        return $strings;
    }

    public function lastHeader(string $name): ?string
    {
        return $this->lastRequest()->headers[$name] ?? null;
    }

    /**
     * Parses the multipart body of the last request into field name => value
     * pairs (file parts include their contents).
     *
     * @return array<string, string>
     */
    public function lastMultipartFields(): array
    {
        $body = (string) $this->lastRequest()->body;
        $pattern = '/name="(?P<name>[^"]+)"(?:; filename="[^"]*")?\r\n(?:Content-Type: [^\r\n]+\r\n)?\r\n(?P<value>.*?)\r\n--/s';

        if (preg_match_all($pattern, $body, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $fields = [];
        foreach ($matches as $match) {
            $fields[(string) $match['name']] = (string) $match['value'];
        }

        return $fields;
    }

    /**
     * How many parts carry the given field name (used for repeated uploads).
     */
    public function lastMultipartPartCount(string $field): int
    {
        return substr_count((string) $this->lastRequest()->body, 'name="' . $field . '"');
    }

    /**
     * @param array<mixed>|string $payload
     */
    private static function encode(array|string $payload): string
    {
        return is_string($payload) ? $payload : (string) json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
