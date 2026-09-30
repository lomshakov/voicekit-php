<?php

declare(strict_types=1);

namespace VoiceKit\Streaming;

use JsonException;
use VoiceKit\Result;
use VoiceKit\VoiceKitError;

/**
 * A bidirectional streaming session (transcription or VAD).
 *
 * The transport is raw PCM16 audio (16 kHz, mono, little-endian) up and JSON
 * events (`session`, `vad`, `partial`, `final`, `error`) down:
 *
 * ```php
 * $stream = $client->transcribeStream(language: 'ru');
 *
 * foreach ($chunks as $pcm16) {
 *     $stream->sendAudio($pcm16);
 * }
 * $stream->stop(); // finalize the last utterance
 *
 * while (($event = $stream->receive()) !== null) {
 *     echo $event->str('type'), ': ', $event->str('text'), PHP_EOL;
 * }
 * $stream->close();
 * ```
 */
final class StreamSession
{
    public function __construct(private readonly WebSocket $socket)
    {
    }

    /**
     * Sends a raw PCM16 frame (16 kHz, mono, little-endian).
     *
     * @throws VoiceKitError
     */
    public function sendAudio(string $pcm16): void
    {
        $this->socket->sendBinary($pcm16);
    }

    /**
     * Sends a control message; arrays are JSON-encoded.
     *
     * @param array<string, mixed>|string $message
     *
     * @throws VoiceKitError
     */
    public function sendJson(array|string $message): void
    {
        if (is_string($message)) {
            $this->socket->sendText($message);

            return;
        }

        try {
            $payload = json_encode($message, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (JsonException $exception) {
            throw new VoiceKitError('VoiceKit: the stream message could not be encoded: ' . $exception->getMessage(), null, null, null, $exception);
        }

        $this->socket->sendText($payload);
    }

    /**
     * Signals the end of speech so the server finalizes the utterance.
     *
     * @throws VoiceKitError
     */
    public function stop(): void
    {
        $this->sendJson(['type' => 'stop']);
    }

    /**
     * Reads the next event, or `null` once the server closes the session.
     *
     * `session` events carry `session_id`; `final` events carry `text` and
     * `segments`; `error` events carry `code` (`quota_exceeded`, `stream_failed`).
     *
     * @throws VoiceKitError
     */
    public function receive(): ?Result
    {
        $payload = $this->socket->receive();
        if ($payload === null) {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (is_array($decoded) && !array_is_list($decoded)) {
            /** @var array<string, mixed> $decoded */
            return new Result($decoded);
        }

        return new Result(['raw' => $payload]);
    }

    /**
     * Whether the session is still open.
     */
    public function isOpen(): bool
    {
        return $this->socket->isOpen();
    }

    /**
     * Sends a close frame and closes the socket.
     */
    public function close(): void
    {
        $this->socket->close();
    }
}
