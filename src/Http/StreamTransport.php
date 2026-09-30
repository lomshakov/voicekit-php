<?php

declare(strict_types=1);

namespace VoiceKit\Http;

use VoiceKit\VoiceKitError;

/**
 * Dependency-free fallback transport built on PHP's HTTP stream wrapper
 * (needs `allow_url_fopen=1`). It is used automatically when the curl
 * extension is missing.
 */
final class StreamTransport implements Transport
{
    /**
     * @param array<string, mixed> $contextOptions extra stream context options, e.g.
     *                                             `['ssl' => ['cafile' => '/etc/ssl/certs/ca.pem']]`.
     */
    public function __construct(
        private readonly float $timeout = 120.0,
        private readonly array $contextOptions = [],
    ) {
    }

    public function send(Request $request): Response
    {
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $context = stream_context_create($this->contextOptions + [
            'http' => [
                'method' => $request->method,
                'header' => implode("\r\n", $headerLines),
                'content' => $request->body ?? '',
                'timeout' => $this->timeout,
                'ignore_errors' => true,
                'follow_location' => 0,
            ],
        ]);

        $body = @file_get_contents($request->url, false, $context);
        /** @var list<string> $rawHeaders */
        $rawHeaders = $http_response_header;
        $parsed = Headers::parse($rawHeaders);

        if ($body === false) {
            $error = error_get_last();
            $message = $error !== null ? $error['message'] : 'the request failed';

            throw VoiceKitError::fromTransport($message);
        }

        if ($parsed['status'] === 0) {
            throw VoiceKitError::fromTransport('the response carried no HTTP status line.');
        }

        return new Response($parsed['status'], $body, $parsed['headers']);
    }
}
