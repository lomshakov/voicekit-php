<?php

declare(strict_types=1);

namespace VoiceKit\Http;

use CurlHandle;
use VoiceKit\VoiceKitError;

use function curl_error;
use function curl_errno;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function extension_loaded;

use const CURLINFO_RESPONSE_CODE;
use const CURLOPT_CONNECTTIMEOUT_MS;
use const CURLOPT_CUSTOMREQUEST;
use const CURLOPT_ENCODING;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_HEADERFUNCTION;
use const CURLOPT_HTTPHEADER;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT_MS;
use const CURLOPT_URL;

/**
 * Default transport: the cURL extension. It keeps connections alive between
 * calls and handles gzip, TLS and timeouts out of the box.
 *
 * ```php
 * $client = new VoiceKitClient('rtt_…', transport: new CurlTransport(timeout: 60.0));
 * ```
 */
final class CurlTransport implements Transport
{
    /**
     * @param array<int, mixed> $curlOptions extra options, e.g. `[CURLOPT_PROXY => '…']`.
     */
    public function __construct(
        private readonly float $timeout = 120.0,
        private readonly array $curlOptions = [],
    ) {
    }

    public function send(Request $request): Response
    {
        if (!extension_loaded('curl')) {
            throw VoiceKitError::fromTransport('the curl extension is not installed; use StreamTransport instead.');
        }

        $handle = curl_init();
        if (!$handle instanceof CurlHandle) {
            throw VoiceKitError::fromTransport('curl_init() failed.');
        }

        $responseHeaders = [];
        $status = 0;

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        $timeoutMs = (int) round(max($this->timeout, 0.001) * 1000);

        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_TIMEOUT_MS => $timeoutMs,
            CURLOPT_CONNECTTIMEOUT_MS => min($timeoutMs, 30_000),
            CURLOPT_ENCODING => '',
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADERFUNCTION => static function (CurlHandle $_, string $line) use (&$responseHeaders, &$status): int {
                $length = strlen($line);
                $trimmed = trim($line);

                if ($trimmed === '') {
                    return $length;
                }

                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $trimmed, $matches) === 1) {
                    // A redirect chain emits several status lines: the last one wins.
                    $status = (int) $matches[1];
                    $responseHeaders = [];

                    return $length;
                }

                $separator = strpos($trimmed, ':');
                if ($separator !== false) {
                    $responseHeaders[strtolower(trim(substr($trimmed, 0, $separator)))] = trim(substr($trimmed, $separator + 1));
                }

                return $length;
            },
        ];

        foreach ($this->curlOptions as $option => $value) {
            $options[$option] = $value;
        }

        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        if (!is_string($body)) {
            $message = sprintf('curl error %d: %s', curl_errno($handle), curl_error($handle));

            throw VoiceKitError::fromTransport($message);
        }

        if ($status === 0) {
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        }

        return new Response($status, $body, $responseHeaders);
    }
}
