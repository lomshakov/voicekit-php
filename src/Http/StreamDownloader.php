<?php

declare(strict_types=1);

namespace VoiceKit\Http;

use Generator;
use VoiceKit\VoiceKitError;

/**
 * Streams a response body without buffering it.
 *
 * Unlike {@see CurlTransport}, which returns the whole body at once, this emits
 * chunks as the server produces them — used by
 * {@see \VoiceKit\VoiceKitClient::synthesizeStream()}.
 *
 * @internal
 */
final class StreamDownloader
{
    /**
     * @return Generator<int, string>
     *
     * @throws VoiceKitError
     */
    public static function download(Request $request, float $timeout, int $chunkSize = 8192): Generator
    {
        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $options = [
            'method' => $request->method,
            'header' => implode("\r\n", $headerLines),
            'timeout' => $timeout,
            'protocol_version' => 1.1,
            'ignore_errors' => true,
            'follow_location' => 0,
        ];

        if ($request->body !== null) {
            $options['content'] = $request->body;
        }

        $context = stream_context_create(['http' => $options]);
        $handle = @fopen($request->url, 'rb', false, $context);

        if ($handle === false) {
            $error = error_get_last();

            throw VoiceKitError::fromTransport($error !== null ? $error['message'] : 'the request failed');
        }

        /** @var list<string> $rawHeaders */
        $rawHeaders = $http_response_header;
        $parsed = Headers::parse($rawHeaders);

        if ($parsed['status'] < 200 || $parsed['status'] > 299) {
            $body = stream_get_contents($handle);
            fclose($handle);

            throw VoiceKitError::fromResponse($parsed['status'], $body === false ? '' : $body);
        }

        $readSize = max(1, $chunkSize);

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, $readSize);
                if ($chunk === false) {
                    throw VoiceKitError::fromTransport('the response stream could not be read.');
                }

                if ($chunk !== '') {
                    yield $chunk;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
