<?php

declare(strict_types=1);

namespace VoiceKit;

use RuntimeException;
use Throwable;

/**
 * Raised for every failed VoiceKit call.
 *
 * Non-2xx API responses follow RFC 7807 Problem Details, so {@see VoiceKitError::errorCode()}
 * carries the machine-readable code (`quota_exceeded`, `streaming_forbidden`, …) and
 * transport failures (DNS, TLS, timeouts) carry a message with no status code.
 *
 * ```php
 * try {
 *     $client->synthesizeStream('Очень длинный текст.');
 * } catch (VoiceKitError $e) {
 *     if ($e->isForbidden()) {
 *         echo 'upgrade your plan: ', $e->errorCode(); // streaming_forbidden
 *     } elseif ($e->isCode('quota_exceeded')) {
 *         echo 'monthly quota is over';
 *     } elseif ($e->isRateLimited()) {
 *         echo 'slow down';
 *     }
 * }
 * ```
 */
class VoiceKitError extends RuntimeException
{
    private ?int $statusCode;

    private ?string $errorCode;

    private ?string $responseBody;

    public function __construct(
        string $message,
        ?int $statusCode = null,
        ?string $errorCode = null,
        ?string $responseBody = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode ?? 0, $previous);
        $this->statusCode = $statusCode;
        $this->errorCode = $errorCode;
        $this->responseBody = $responseBody;
    }

    /**
     * Builds an error from a non-2xx API response.
     */
    public static function fromResponse(int $statusCode, string $body): self
    {
        $errorCode = null;
        $message = '';

        if ($body !== '') {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $candidate = $decoded['code'] ?? null;
                if (is_string($candidate) && $candidate !== '') {
                    $errorCode = $candidate;
                }

                foreach (['detail', 'title', 'message', 'error'] as $key) {
                    $value = $decoded[$key] ?? null;
                    if (is_string($value) && $value !== '') {
                        $message = $value;
                        break;
                    }
                }
            }

            if ($message === '' && !str_starts_with(ltrim($body), '{') && strlen($body) <= 512) {
                $message = trim($body);
            }
        }

        if ($message === '') {
            $message = self::reasonPhrase($statusCode);
        }

        return new self(
            sprintf(
                'VoiceKit API error: %s (HTTP %d%s)',
                $message,
                $statusCode,
                $errorCode !== null ? ', code ' . $errorCode : '',
            ),
            $statusCode,
            $errorCode,
            $body !== '' ? $body : null,
        );
    }

    /**
     * Builds an error for a network-level failure (no response was received).
     */
    public static function fromTransport(string $message, ?Throwable $previous = null): self
    {
        return new self('VoiceKit transport error: ' . $message, null, null, null, $previous);
    }

    /**
     * HTTP status of the failed response, or `null` for transport failures.
     */
    public function statusCode(): ?int
    {
        return $this->statusCode;
    }

    /**
     * Machine-readable error code from the response body, or `null` when absent.
     */
    public function errorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * Raw response body, or `null` when the request never reached the API.
     */
    public function responseBody(): ?string
    {
        return $this->responseBody;
    }

    /**
     * Whether the response carried the given error code.
     */
    public function isCode(string $code): bool
    {
        return $this->errorCode === $code;
    }

    /**
     * Whether any exception is a {@see VoiceKitError} with the given code.
     */
    public static function matches(Throwable $error, string $code): bool
    {
        return $error instanceof self && $error->isCode($code);
    }

    /**
     * Whether the API answered `401` (missing or invalid API key).
     */
    public function isUnauthorized(): bool
    {
        return $this->statusCode === 401;
    }

    /**
     * Whether the API answered `403` (the plan does not include the feature).
     */
    public function isForbidden(): bool
    {
        return $this->statusCode === 403;
    }

    /**
     * Whether the API answered `404`.
     */
    public function isNotFound(): bool
    {
        return $this->statusCode === 404;
    }

    /**
     * Whether the API answered `429` (rate limit hit).
     */
    public function isRateLimited(): bool
    {
        return $this->statusCode === 429;
    }

    private static function reasonPhrase(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            408 => 'Request Timeout',
            409 => 'Conflict',
            413 => 'Payload Too Large',
            415 => 'Unsupported Media Type',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            default => 'HTTP error',
        };
    }
}
